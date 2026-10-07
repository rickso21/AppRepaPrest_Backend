<?php

namespace App\Http\Controllers\comunidad;

use App\Events\CommentCreated;
use App\Http\Controllers\Controller;
use App\Http\Requests\comunidad\commentRequest;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class commentController extends Controller
{
    // CREAR COMENTARIO
    public function store(commentRequest $request, Post $post)
    {
        $user_token = $request->user();

        if ($response = comment_validate_auth($user_token)) {
            return $response;
        }

        if ($response = comment_validate_post($post)) {
            return $response;
        }

        try {
            $comment = $post->comments()->create([
                'user_id' => $user_token->id,
                'post_id' => $post->id,
                'comment' => $request->comment,
                'activo'  => 1,
            ]);

            broadcast(new CommentCreated($comment))->toOthers();

            return comment_success_response(
                'Comentario agregado',
                comment_format_payload($comment, $user_token)
            );

        } catch (\Throwable $th) {
            comment_log_error('comment.store', $th);

            return comment_error_response('Error al guardar el comentario', 500);
        }
    }

    // ELIMINAR COMENTARIO
    public function destroy(int $id, Request $request)
    {
        $user_token = $request->user();

        if ($response = comment_validate_auth($user_token)) {
            return $response;
        }

        $comment = comment_find_or_fail($id);

        if ($comment instanceof JsonResponse) {
            return $comment;
        }

        if ($response = comment_validate_ownership($user_token, $comment)) {
            return $response;
        }

        $comment->delete();

        return comment_success_response('Comentario eliminado');
    }

    // ACTUALIZAR COMENTARIO
    public function update(int $id, commentRequest $request)
    {
        $user_token = $request->user();

        if ($response = comment_validate_auth($user_token)) {
            return $response;
        }

        $comment = comment_find_or_fail($id);

        if ($comment instanceof JsonResponse) {
            return $comment;
        }

        if ($response = comment_validate_ownership($user_token, $comment)) {
            return $response;
        }

        $comment->comment = $request->comment;
        $comment->save();

        return comment_success_response('Comentario actualizado');
    }
}
