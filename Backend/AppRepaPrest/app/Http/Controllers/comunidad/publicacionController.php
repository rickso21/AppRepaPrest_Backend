<?php

namespace App\Http\Controllers\comunidad;

use App\Events\PostCreated;
use App\Events\PostReacted;
use App\Events\PostDeleted;
use App\Http\Controllers\Controller;
use App\Http\Requests\comunidad\savePublishRequest;
use App\Models\Post;
use App\Models\PostReaction;
use App\Models\User;
use App\Notifications\NewPostPublished;
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

    // ================================================================
    // LISTAR
    // ================================================================
    public function index(Request $request)
    {
        $user_token = $request->user();

        if (!$user_token) {
            return response()->json(['res' => false, 'msg' => 'Usuario no autenticado'], 401);
        }

        $user_token->loadMissing('grupo');

        $scope = $request->input('scope', 'group');
        if (!in_array($scope, ['group', 'global'], true)) {
            $scope = 'group';
        }

        $query = Post::where('activo', 1);

        if ($scope === 'group') {
            $gruposVisibles = $this->gruposVisiblesDe($user_token);

            $grupoFiltro = $request->input('group_id');

            if ($grupoFiltro && in_array((int) $grupoFiltro, $gruposVisibles, true)) {
                $query->where('group_id', (int) $grupoFiltro);
            } else {
                $query->whereIn('group_id', $gruposVisibles);
            }

            $query->whereHas('user', fn($q) => $q->where('status_id', 1));
        } else {
            $idsPrincipales = \App\Models\Grupo::where('tipo_grupo', \App\Models\Grupo::TIPO_PRINCIPAL)
                ->pluck('id')
                ->all();

            $query->whereIn('group_id', $idsPrincipales)
                ->whereHas('user', fn($q) => $q->where('status_id', 1));
        }

        $posts = $query
            ->with([
                'user',
                'reactions',
                'comments' => function ($q) {
                    $q->where('activo', 1)
                      ->whereHas('user', fn($qq) => $qq->where('status_id', 1))
                      ->with('user');
                },
            ])
            ->orderBy('created_at', 'desc')
            ->get();

        $publicaciones = $posts->map(
            fn($post) => $this->formatearPost($post, $user_token, $scope)
        );

        return response()->json([
            'res'           => true,
            'grupo'         => $user_token->grupo?->group_name,
            'scope'         => $scope,
            'img_grupo'     => $user_token->grupo
                ? public_path('/img/group/' . $user_token->grupo->img_group)
                : null,
            'publicaciones' => $publicaciones,
        ], 200);
    }

    // ================================================================
    // CREAR
    // ================================================================
    public function store(savePublishRequest $request)
    {
        $user_token = $request->user();

        if (!$user_token) {
            return response()->json(['res' => false, 'msg' => 'Usuario no autenticado'], 401);
        }

        // 1) Validar permisos ANTES de tocar disco
        $groupDestino = (int) $request->input('group_id', $user_token->grupo_id);

        if (!$this->usuarioPuedePublicarEn($user_token, $groupDestino)) {
            return response()->json([
                'res' => false,
                'msg' => 'No tienes permiso para publicar en ese grupo',
            ], 403);
        }

        // 2) Instanciar post y guardar archivos
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

        if ($request->hasFile('audio')) {
            $post->audio = $this->guardarArchivo(
                $request->file('audio'),
                'audio/publish',
                time() . '_' . $user_token->id . '_audio'
            );
        }

        // 3) Asignar campos y guardar
        $post->post     = $request->txt;
        $post->user_id  = $user_token->id;
        $post->group_id = $groupDestino;
        $post->activo   = 1;

        try {
            $post->save();

            // 4) Notificar a los demás miembros del grupo.
            //    Debe ser encolado (NewPostPublished implements ShouldQueue)
            User::where('grupo_id', $post->group_id)
                ->where('id', '!=', $post->user_id)
                ->whereNotNull('expo_push_token')
                ->where('expo_push_token', '!=', '')
                ->get()
                ->each(fn($user) => $user->notify(new NewPostPublished($post)));

            // 5) Broadcast (encolado por el propio Event al implementar ShouldQueue)
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

    // ================================================================
    // ACTUALIZAR
    // ================================================================
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

            if ($request->hasFile('audio')) {
                $post->audio = $this->guardarArchivo(
                    $request->file('audio'),
                    'audio/publish',
                    time() . '_' . $user_token->id . '_audio'
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

    // ================================================================
    // ELIMINAR
    // ================================================================
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

            broadcast(new PostDeleted($postId, $groupId, $userId))->toOthers();

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

    // ================================================================
    // REACCIONAR
    // ================================================================
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

        if (!$post || !$this->usuarioPuedePublicarEn($user, (int) $post->group_id)) {
            return response()->json(['res' => false, 'msg' => 'No encontrado'], 404);
        }

        $existente = PostReaction::where('post_id', $id)
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
        $comentarios = $post->comments
            ->map(fn($c) => $this->formatearComentario($c, $scope))
            ->toArray();

        $reacciones = $this->contarReacciones($post, $user_token->id);

        $autor       = $post->user;
        $nombreAutor = $this->nombrePublico($autor, $scope);

        if ($scope === 'group') {
            $user_data = [
                'id'         => $autor->id,
                'nombre'     => $nombreAutor,
                'avatar_url' => $autor->avatar_url ?? null,
            ];
        } else {
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
            'audio'   => $post->audio,
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

    private function formatearPostNuevo(Post $post, $user_token): array
    {
        $nombreAutor = $this->nombreCompleto($user_token);

        return [
            'id'      => $post->id,
            'user_id' => $post->user_id,
            'post'    => $post->post,
            'image'   => $post->image,
            'video'   => $post->video,
            'audio'   => $post->audio,
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

            'links' => $this->extraerLinksDetectados($post->post),
        ];
    }

    private function formatearComentario($comment, string $scope = 'group'): array
    {
        $autor       = $comment->user;
        $nombreAutor = $this->nombrePublico($autor, $scope);

        return [
            'nombre'     => $nombreAutor,
            'comentario' => $comment->comment,
            'fecha'      => $comment->created_at->format('d/m/Y'),
            'hora'       => $comment->created_at->format('H:i'),
            'avatar_url' => $scope === 'group' ? ($autor->avatar_url ?? null) : null,
        ];
    }

    private function contarReacciones(Post $post, int $userId): array
    {
        $counts     = self::REACCIONES_VACIAS;
        $miReaccion = null;

        foreach ($post->reactions as $r) {
            if (isset($counts[$r->type])) $counts[$r->type]++;
            if ($r->user_id === $userId) $miReaccion = $r->type;
        }

        return ['counts' => $counts, 'mi_reaccion' => $miReaccion];
    }

    private function guardarArchivo($file, string $carpeta, string $baseName): string
    {
        $ext  = strtolower($file->getClientOriginalExtension() ?: 'bin');
        $name = $baseName . '.' . $ext;
        $size = $file->getSize();
        $mime = $file->getMimeType();

        $path = public_path($carpeta);
        if (!file_exists($path)) {
            mkdir($path, 0775, true);
        }

        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $this->comprimirImagen($file->getRealPath(), $path . '/' . $name, $ext);
        } else {
            $file->move($path, $name);
        }

        \Log::info('[publicacion] Archivo guardado', [
            'carpeta' => $carpeta,
            'file'    => $name,
            'size'    => $size,
            'mime'    => $mime,
        ]);

        return $name;
    }

    private function comprimirImagen(string $origen, string $destino, string $ext): void
    {
        if (!function_exists('imagecreatefromstring')) {
            copy($origen, $destino);
            return;
        }

        $data = file_get_contents($origen);
        $img  = @imagecreatefromstring($data);

        if (!$img) {
            copy($origen, $destino);
            return;
        }

        $w    = imagesx($img);
        $h    = imagesy($img);
        $maxW = 1600;

        if ($w > $maxW) {
            $nuevoH = (int) ($h * ($maxW / $w));
            $tmp    = imagecreatetruecolor($maxW, $nuevoH);
            imagecopyresampled($tmp, $img, 0, 0, 0, 0, $maxW, $nuevoH, $w, $h);
            imagedestroy($img);
            $img = $tmp;
        }

        switch ($ext) {
            case 'png':
                imagepng($img, $destino, 7);
                break;
            case 'webp':
                imagewebp($img, $destino, 80);
                break;
            default:
                imagejpeg($img, $destino, 80);
        }

        imagedestroy($img);
    }

    private function nombreCompleto($user): string
    {
        return trim(
            ($user->nombre ?? '') . ' ' .
            ($user->apellido_p ?? '') . ' ' .
            ($user->apellido_m ?? '')
        );
    }

    private function extraerUrls(string $texto): array
    {
        if (empty($texto)) return [];

        $pattern = '/https?:\/\/[^\s<>"\')\]]+/i';
        preg_match_all($pattern, $texto, $matches);

        return array_values(array_unique($matches[0] ?? []));
    }

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

    private function extraerLinksDetectados(?string $texto): array
    {
        return array_values(array_filter(
            array_map(
                fn($url) => $this->detectarTipoLink($url),
                $this->extraerUrls($texto ?? '')
            )
        ));
    }

    private function nombrePublico($user, string $scope = 'group'): string
    {
        if ($scope === 'global') {
            return trim($user->nombre ?? 'Usuario');
        }

        return $this->nombreCompleto($user);
    }

    private function gruposVisiblesDe($user): array
    {
        if (!$user->grupo_id) return [];

        return \App\Models\Grupo::gruposVisiblesDe((int) $user->grupo_id);
    }

    private function usuarioPuedePublicarEn($user, int $grupoId): bool
    {
        if (!$user->grupo_id) return false;

        $visibles = \App\Models\Grupo::gruposVisiblesDe((int) $user->grupo_id);

        return in_array($grupoId, $visibles, true);
    }
}
