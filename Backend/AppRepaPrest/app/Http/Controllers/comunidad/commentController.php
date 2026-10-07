<?php

namespace App\Http\Controllers\comunidad;

use App\Events\CommentCreated;
use App\Http\Controllers\Controller;
use App\Http\Requests\comunidad\commentRequest;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\Request;

class commentController extends Controller
{
    // ================================================================
    // CREAR COMENTARIO
    // ================================================================
    public function store(commentRequest $request, Post $post)
    {
        $user_token = $request->user();

        if (!$user_token) {
            return response()->json([
                'res' => false,
                'msg' => 'Usuario no autenticado',
            ], 401);
        }

        if (!$post || !$post->activo) {
            return response()->json([
                'res' => false,
                'msg' => 'Publicación no encontrada o inactiva',
            ], 404);
        }

        try {
            $comment = $post->comments()->create([
                'user_id' => $user_token->id,
                'post_id' => $post->id,
                'comment' => $request->comment,
                'activo'  => 1,
            ]);

            // Emitir el evento al canal del grupo del post
            broadcast(new CommentCreated($comment))->toOthers();

            return response()->json([
                'res' => true,
                'msg' => 'Comentario agregado',
                'data' => [
                    'id'         => $comment->id,
                    'nombre'     => trim(
                        ($user_token->nombre ?? '') . ' ' .
                        ($user_token->apellido_p ?? '') . ' ' .
                        ($user_token->apellido_m ?? '')
                    ),
                    'comentario' => $comment->comment,
                    'fecha'      => $comment->created_at->format('d/m/Y'),
                    'hora'       => $comment->created_at->format('H:i'),
                    'avatar_url' => $user_token->avatar_url ?? null,
                ],
            ], 200);

        } catch (\Throwable $th) {
            \Log::error('[comment.store] Error', ['error' => $th->getMessage()]);

            return response()->json([
                'res' => false,
                'msg' => 'Error al guardar el comentario',
            ], 500);
        }
    }

    // ================================================================
    // ELIMINAR COMENTARIO
    // ================================================================

    public function destroy(int $id, Request $request)
    {
        $user_token = $request->user();

        if (!$user_token) {
            return response()->json([
                'res' => false,
                'msg' => 'Usuario no autenticado',
            ], 401);
        }

        $comment = Comment::find($id);

        if (!$comment) {
            return response()->json([
                'res' => false,
                'msg' => 'Comentario no existe',
            ], 404);
        }

        if ($user_token->id != $comment->user_id) {
            return response()->json([
                'res' => false,
                'msg' => 'No autorizado',
            ], 403);
        }

        $comment->delete();

        return response()->json([
            'res' => true,
            'msg' => 'Comentario eliminado',
        ], 200);
    }

    // ================================================================
    // ACTUALIZAR COMENTARIO
    // ================================================================

    public function update(int $id, commentRequest $request)
    {
        $user_token = $request->user();

        if (!$user_token) {
            return response()->json([
                'res' => false,
                'msg' => 'Usuario no autenticado',
            ], 401);
        }

        $comment = Comment::find($id);

        if (!$comment) {
            return response()->json([
                'res' => false,
                'msg' => 'Comentario no existe',
            ], 404);
        }

        if ($user_token->id != $comment->user_id) {
            return response()->json([
                'res' => false,
                'msg' => 'No autorizado',
            ], 403);
        }

        $comment->comment = $request->comment;
        $comment->save();

        return response()->json([
            'res' => true,
            'msg' => 'Comentario actualizado',
        ], 200);
    }
}
