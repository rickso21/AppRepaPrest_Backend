<?php

namespace App\Http\Controllers\comunidad;

use App\Events\PostCreated;
use App\Events\PostReacted;
use App\Events\PostDeleted;

use App\Http\Controllers\Controller;
use App\Http\Requests\comunidad\savePublishRequest;
use App\Models\Post;
use App\Models\PostReaction;
use Illuminate\Http\Request;

class publicacionController extends Controller
{
    // ================================================================
    // CONSTANTES
    // ================================================================
    private const REACCIONES_VALIDAS = ['like', 'love', 'haha', 'sad', 'angry'];

    private const REACCIONES_VACIAS = [
        'like'  => 0,
        'love'  => 0,
        'haha'  => 0,
        'sad'   => 0,
        'angry' => 0,
    ];


    public function index(Request $request)
    {
        $user_token = $request->user();

        if (!$user_token) {
            return response()->json(['res' => false, 'msg' => 'Usuario no autenticado'], 401);
        }

        $scope = $request->input('scope', 'group');

        if (!in_array($scope, ['group', 'global'])) {
            $scope = 'group';
        }


        $query = Post::where('activo', 1);

        if ($scope === 'group') {
            $query->where('group_id', $user_token->grupo_id)
                ->whereHas('user', fn($q) => $q->where('status_id', 1));
        } else {

            $query->whereHas('user', fn($q) => $q->whereNotNull('id'));
        }

        $posts = $query->orderBy('created_at', 'desc')->get();


       $publicaciones = $posts->map(function ($post) use ($user_token, $scope) {
    return $this->formatearPost($post, $user_token, $scope);
});

        return response()->json([
            'res'           => true,
            'grupo'         => $user_token->grupo->group_name,
            'scope'         => $scope,
            'img_grupo'     => public_path('/img/group/' . $user_token->grupo->img_group),
            'publicaciones' => $publicaciones,
        ], 200);
    }

    public function store(savePublishRequest $request)
    {
        $user_token = $request->user();

        if (!$user_token) {
            return response()->json(['res' => false, 'msg' => 'Usuario no autenticado'], 401);
        }

        $post = new Post();

        if ($request->hasFile('img')) {
            $post->image = $this->guardarArchivo(
                $request->file('img'),
                'img/publish',
                time() . '_' . $user_token->id
            );
        }

        if ($request->hasFile('video')) {
            $post->video = $this->guardarArchivo(
                $request->file('video'),
                'img/publish',
                time() . '_' . $user_token->id . '_video'
            );
        }

        // 3. Guardar post
        $post->post     = $request->txt;
        $post->user_id  = $user_token->id;
        $post->group_id = $user_token->grupo_id;
        $post->activo   = 1;

        try {
            $post->save();

            \Log::info('[publicacion.store] Post guardado', [
                'id'    => $post->id,
                'image' => $post->image,
                'video' => $post->video,
            ]);

            broadcast(new PostCreated($post))->toOthers();

            return response()->json([
                'res'  => true,
                'msg'  => 'Publicación guardada correctamente',
                'data' => $this->formatearPostNuevo($post, $user_token),
            ], 200);
        } catch (\Throwable $th) {
            \Log::error('[publicacion.store] Error', ['error' => $th->getMessage()]);

            return response()->json([
                'res' => false,
                'msg' => $th->getMessage(),
            ], 409);
        }
    }


    public function update(savePublishRequest $request, $id)
    {
        $user_token = $request->user();

        if (!$user_token) {
            return response()->json(['res' => false, 'msg' => 'Usuario no autenticado'], 401);
        }

        $post = Post::find($id);

        if (!$post) {
            return response()->json(['res' => false, 'msg' => 'Publicación no encontrada'], 404);
        }

        if ($post->user_id != $user_token->id) {
            return response()->json([
                'res' => false,
                'msg' => 'No tienes permiso para editar esta publicación',
            ], 403);
        }

        try {
            $post->post = $request->txt;

            if ($request->hasFile('img')) {
                $post->image = $this->guardarArchivo(
                    $request->file('img'),
                    'img/publish',
                    time() . '_' . $user_token->id
                );
            }

            if ($request->hasFile('video')) {
                $post->video = $this->guardarArchivo(
                    $request->file('video'),
                    'img/publish',
                    time() . '_' . $user_token->id . '_video'
                );
            }

            $post->save();

            return response()->json([
                'res' => true,
                'msg' => 'Publicación actualizada correctamente',
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'res' => false,
                'msg' => $th->getMessage(),
            ], 409);
        }
    }


    public function destroy(Request $request, $id)
    {
        $user_token = $request->user();

        if (!$user_token) {
            return response()->json(['res' => false, 'msg' => 'Usuario no autenticado'], 401);
        }

        $post = Post::find($id);

        if (!$post) {
            return response()->json(['res' => false, 'msg' => 'Publicación no encontrada'], 404);
        }

        if ($post->user_id != $user_token->id) {
            return response()->json([
                'res' => false,
                'msg' => 'No tienes permiso para eliminar esta publicación',
            ], 403);
        }

        try {
            $postId  = $post->id;
            $groupId = $post->group_id;
            $userId  = $post->user_id;

            $post->delete();

            broadcast(new \App\Events\PostDeleted($postId, $groupId, $userId))->toOthers();

            \Log::info('[publicacion.destroy] Post eliminado', [
                'id'       => $postId,
                'group_id' => $groupId,
            ]);

            return response()->json([
                'res' => true,
                'msg' => 'Publicación eliminada correctamente',
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'res' => false,
                'msg' => $th->getMessage(),
            ], 500);
        }
    }


    public function reaccionar(Request $request, $id)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['res' => false, 'msg' => 'No autenticado'], 401);
        }

        $request->validate([
            'type' => 'required|in:' . implode(',', self::REACCIONES_VALIDAS),
        ]);

        $post = Post::find($id);

        if (!$post || $post->group_id !== $user->grupo_id) {
            return response()->json(['res' => false, 'msg' => 'No encontrado'], 404);
        }

        $existente    = PostReaction::where('post_id', $id)
            ->where('user_id', $user->id)
            ->first();

        $tipoAnterior = $existente?->type;
        $tipoFinal    = $request->type;

        if ($existente && $existente->type === $request->type) {
            $existente->delete();
            $accion    = 'removed';
            $tipoFinal = null;
        } elseif ($existente) {
            $existente->update(['type' => $request->type]);
            $accion = 'updated';
        } else {
            PostReaction::create([
                'post_id' => $id,
                'user_id' => $user->id,
                'type'    => $request->type,
            ]);
            $accion = 'added';
        }

        broadcast(new PostReacted($post, $user->id, $tipoFinal, $accion, $tipoAnterior))
            ->toOthers();

        return response()->json([
            'res'    => true,
            'accion' => $accion,
            'type'   => $tipoFinal,
        ]);
    }

    // ================================================================
    // HELPERS PRIVADOS
    // ================================================================

    private function formatearPost(Post $post, $user_token, string $scope = 'group'): array
{
    $comentarios = $post->comments()
        ->where('activo', 1)
        ->whereHas('user', fn($q) => $q->where('status_id', 1))
        ->get()
        ->map(fn($c) => $this->formatearComentario($c, $scope))   // 👈 pasar scope
        ->toArray();

    $reacciones = $this->contarReacciones($post, $user_token->id);

    $autor = $post->user;

    $nombreAutor = $this->nombrePublico($autor, $scope);

    if ($scope === 'group') {
        $user_data = [
            'id'         => $autor->id,
            'nombre'     => $nombreAutor,
            'avatar_url' => $autor->avatar_url ?? null,
        ];
    } else {
        // En global: sin id, sin avatar (para no exponer identidad)
        $user_data = [
            'nombre'     => $nombreAutor,
            'avatar_url' => $autor->avatar_url ?? null,
        ];
    }

    return [
        'id'      => $post->id,
        'user_id' => $scope === 'group' ? $post->user_id : null,
        'post'    => $post->post,
        'image'   => $post->image,
        'video'   => $post->video,
        'user'    => $nombreAutor,

        'user_data' => $user_data,

        'fecha'       => $post->created_at->format('d/m/Y'),
        'hora'        => $post->created_at->format('H:i'),
        'comentarios' => $comentarios,
        'reacciones'  => $reacciones['counts'],
        'mi_reaccion' => $reacciones['mi_reaccion'],

        'links' => $this->extraerLinksDetectados($post->post),
    ];
}

    /**
     * Formatea un post recién creado.
     */
    private function formatearPostNuevo(Post $post, $user_token): array
    {
        $nombreAutor = $this->nombreCompleto($user_token);

        return [
            'id'      => $post->id,
            'user_id' => $post->user_id,
            'post'    => $post->post,
            'image'   => $post->image,
            'video'   => $post->video,
            'user'    => $nombreAutor,

            'user_data' => [
                'id'         => $user_token->id,
                'nombre'     => $nombreAutor,
                'avatar_url' => $user_token->avatar_url ?? null,
            ],

            'fecha'       => $post->created_at->format('d/m/Y'),
            'hora'        => $post->created_at->format('H:i'),
            'comentarios' => [],
            'reacciones'  => self::REACCIONES_VACIAS,
            'mi_reaccion' => null,

            'links' => $this->extraerLinksDetectados($post->post),   // ✅ funciona
        ];
    }

    /**
     * Formatea un comentario.
     */
    private function formatearComentario($comment, string $scope = 'group'): array
{
    $autor = $comment->user;

    $nombreAutor = $this->nombrePublico($autor, $scope);

    return [
        'nombre'     => $nombreAutor,
        'comentario' => $comment->comment,
        'fecha'      => $comment->created_at->format('d/m/Y'),
        'hora'       => $comment->created_at->format('H:i'),
        'avatar_url' => $scope === 'group' ? ($autor->avatar_url ?? null) : null,
    ];
}

    /**
     * Cuenta las reacciones de un post.
     */
    private function contarReacciones(Post $post, int $userId): array
    {
        $counts     = self::REACCIONES_VACIAS;
        $miReaccion = null;

        foreach ($post->reactions()->get() as $r) {
            if (isset($counts[$r->type])) {
                $counts[$r->type]++;
            }
            if ($r->user_id === $userId) {
                $miReaccion = $r->type;
            }
        }

        return ['counts' => $counts, 'mi_reaccion' => $miReaccion];
    }

    /**
     * Guarda un archivo en `public/{$carpeta}`.
     */
    private function guardarArchivo($file, string $carpeta, string $baseName): string
    {
        $ext  = $file->getClientOriginalExtension() ?: 'bin';
        $name = $baseName . '.' . $ext;
        $size = $file->getSize();          // ✅ antes de mover
        $mime = $file->getMimeType();      // ✅ antes de mover

        $path = public_path($carpeta);
        if (!file_exists($path)) {
            mkdir($path, 0775, true);
        }

        $file->move($path, $name);

        \Log::info('[publicacion] Archivo guardado', [
            'carpeta' => $carpeta,
            'file'    => $name,
            'size'    => $size,
            'mime'    => $mime,
        ]);

        return $name;
    }

    /**
     * Devuelve el nombre completo de un usuario.
     */
    private function nombreCompleto($user): string
    {
        return trim(
            ($user->nombre ?? '') . ' ' .
                ($user->apellido_p ?? '') . ' ' .
                ($user->apellido_m ?? '')
        );
    }

    /**
     * Extrae las URLs del texto de una publicación.
     *
     * @return string[]
     */
    private function extraerUrls(string $texto): array
    {
        if (empty($texto)) return [];

        $pattern = '/https?:\/\/[^\s<>"\')\]]+/i';
        preg_match_all($pattern, $texto, $matches);

        return array_values(array_unique($matches[0] ?? []));
    }

    /**
     * Detecta el tipo de link según el dominio.
     */
    private function detectarTipoLink(string $url): ?array
    {
        $dominios = [
            'youtube'   => '/(youtube\.com|youtu\.be)/i',
            'facebook'  => '/facebook\.com/i',
            'instagram' => '/instagram\.com/i',
            'tiktok'    => '/tiktok\.com/i',
            'twitter'   => '/(twitter\.com|x\.com)/i',
            'vimeo'     => '/vimeo\.com/i',
            'spotify'   => '/spotify\.com/i',
            'linkedin'  => '/linkedin\.com/i',
            'whatsapp'  => '/(wa\.me|whatsapp\.com)/i',
        ];

        foreach ($dominios as $tipo => $regex) {
            if (preg_match($regex, $url)) {
                return ['tipo' => $tipo, 'url' => $url];
            }
        }

        return null;
    }

    /**
     * Combina extraerUrls + detectarTipoLink.
     *
     * @return array<int, array{tipo: string, url: string}>
     */
    private function extraerLinksDetectados(?string $texto): array
    {
        return array_values(array_filter(
            array_map(
                fn($url) => $this->detectarTipoLink($url),
                $this->extraerUrls($texto ?? '')
            )
        ));
    }

    /**
 * Devuelve el nombre del usuario según el scope.
 *
 * - scope = "group"  → nombre completo (nombre + apellidos)
 * - scope = "global" → solo el primer nombre (privacidad)
 */
private function nombrePublico($user, string $scope = 'group'): string
{
    if ($scope === 'global') {
        return trim($user->nombre ?? 'Usuario');
    }

    return $this->nombreCompleto($user);
}
}
