<?php

namespace App\Http\Controllers\comunidad;

use App\Events\PostCreated;
use App\Events\PostDeleted;
use App\Events\PostReacted;
use App\Http\Controllers\Controller;
use App\Http\Requests\comunidad\savePublishRequest;
use App\Models\Post;
use App\Models\PostReaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class publicacionController extends Controller
{
    // LISTADO
    public function index(Request $request)
    {
        $user_token = $request->user();

        if ($response = post_validate_auth($user_token)) {
            return $response;
        }

        $scope = post_normalizar_scope($request->input('scope', 'group'));

        $query = Post::with([
                'user',
                'comments' => fn($q) => $q->where('activo', 1)->with('user'),
                'reactions',
            ])
            ->where('activo', 1);

        if ($scope === 'group') {
            $gruposVisibles = post_grupos_visibles_de($user_token);
            $grupoFiltro    = $request->input('group_id');

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

        $perPage = (int) $request->input('per_page', 20);
        $posts   = $query->orderBy('created_at', 'desc')->paginate($perPage);

        $publicaciones = collect($posts->items())
            ->map(fn($post) => post_formatear($post, $user_token, $scope));

        return response()->json([
            'res'           => true,
            'grupo'         => optional($user_token->grupo)->group_name,
            'scope'         => $scope,
            'img_grupo'     => $user_token->grupo
                ? public_path('/img/group/' . $user_token->grupo->img_group)
                : null,
            'publicaciones' => $publicaciones,
            'pagination'    => [
                'current_page' => $posts->currentPage(),
                'last_page'    => $posts->lastPage(),
                'per_page'     => $posts->perPage(),
                'total'        => $posts->total(),
            ],
        ], 200);
    }

    // CREAR
    public function store(savePublishRequest $request)
    {
        $user_token = $request->user();

        if ($response = post_validate_auth($user_token)) {
            return $response;
        }

        $groupDestino = (int) $request->input('group_id', $user_token->grupo_id);

        if (!post_usuario_puede_publicar_en($user_token, $groupDestino)) {
            return post_error_response('No tienes permiso para publicar en ese grupo', 403);
        }

        $post = new Post();

        post_procesar_archivos_request($post, $request, $user_token->id);

        $post->post     = $request->txt;
        $post->user_id  = $user_token->id;
        $post->group_id = $groupDestino;
        $post->activo   = 1;

        try {
            $post->save();

            // 🔧 FIX #7: notificar se hace en un Job encolado; el request responde YA.
            // 🔧 FIX #9: la respuesta usa el MISMO formato que post_formatear_nuevo
            //    para que el frontend pueda insertar localmente sin refetch.
            post_notificar_miembros_grupo($post);

            \Log::info('[publicacion.store] Post guardado', [
                'id'    => $post->id,
                'image' => $post->image,
                'video' => $post->video,
                'audio' => $post->audio,
            ]);

            broadcast(new PostCreated($post))->toOthers();

            return post_success_response(
                'Publicación guardada correctamente',
                ['data' => post_formatear_nuevo($post, $user_token)]
            );
        } catch (\Throwable $th) {
            \Log::error('[publicacion.store] Error', ['error' => $th->getMessage()]);
            return post_error_response($th->getMessage(), 409);
        }
    }

    // ACTUALIZAR
    public function update(savePublishRequest $request, $id)
    {
        $user_token = $request->user();

        if ($response = post_validate_auth($user_token)) {
            return $response;
        }

        $post = post_find_or_fail($id);

        if ($post instanceof JsonResponse) {
            return $post;
        }

        if ($response = post_validate_ownership($user_token, $post, 'editar')) {
            return $response;
        }

        try {
            $post->post = $request->txt;

            post_procesar_archivos_request($post, $request, $user_token->id);

            $post->save();

            return post_success_response('Publicación actualizada correctamente');
        } catch (\Throwable $th) {
            return post_error_response($th->getMessage(), 409);
        }
    }

    // ELIMINAR
    public function destroy(Request $request, $id)
    {
        $user_token = $request->user();

        if ($response = post_validate_auth($user_token)) {
            return $response;
        }

        $post = post_find_or_fail($id);

        if ($post instanceof JsonResponse) {
            return $post;
        }

        if ($response = post_validate_ownership($user_token, $post, 'eliminar')) {
            return $response;
        }

        try {
            $postId  = $post->id;
            $groupId = $post->group_id;
            $userId  = $post->user_id;

            $post->delete();

            broadcast(new PostDeleted($postId, $groupId, $userId))->toOthers();

            \Log::info('[publicacion.destroy] Post eliminado', [
                'id'       => $postId,
                'group_id' => $groupId,
            ]);

            return post_success_response('Publicación eliminada correctamente');
        } catch (\Throwable $th) {
            return post_error_response($th->getMessage(), 500);
        }
    }

    // REACCIONAR
    public function reaccionar(Request $request, $id)
    {
        $user = $request->user();

        if ($response = post_validate_auth($user)) {
            return $response;
        }

        $request->validate([
            'type' => 'required|in:' . implode(',', post_reacciones_validas()),
        ]);

        $post = Post::find($id);

        if (!$post || !post_usuario_puede_publicar_en($user, (int) $post->group_id)) {
            return post_error_response('No encontrado', 404);
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
}
