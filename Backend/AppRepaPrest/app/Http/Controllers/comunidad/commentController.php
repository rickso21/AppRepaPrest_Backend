<?php

namespace App\Http\Controllers\comunidad;

use App\Http\Controllers\Controller;
use App\Http\Requests\comunidad\commentRequest;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\Request;

class commentController extends Controller
{
    public function store(Post $post, commentRequest $request)
    {
        $resp=['res' => false, 'msg' => 'Usuario no autenticado'];
        $status_resp = 401;
        // BUSCA GRUPO DEL USUARIO
        $user_token = $request->user();
        if (!$user_token) {
            return response()->json($resp, $status_resp);
        }
        if ( $user_token->grupo_id != $post->grupo_id ) {
            return response()->json($resp, $status_resp);
        }
        $post->comments()->create([
            'user_id' => $user_token->id,
            'post_id' => $post->id,
            'comment' => $request->txt,
            'activo' => 1
        ]);
        return response()->json(['res' => true, 'msg' => 'Comentario agregado'], 200);
    }

    // FUNCION PARA ELIMINAR COMENTARIO
    public function destroy(int $id, commentRequest $request)
    {
        $resp=['res' => false, 'msg' => 'No autorizado'];
        $status_resp = 401;
        // BUSCA GRUPO DEL USUARIO
        $user_token = $request->user();
        if (!$user_token) {
            return response()->json($resp, $status_resp);
        }
        $comment = Comment::find($id);
        $resp=['res' => false, 'msg' => 'Comentario no existe'];
        if ( !$comment ) {
            return response()->json($resp, $status_resp);
        }
        if ( $user_token->id != $comment->user_id ) {
            return response()->json($resp, $status_resp);
        }
        $comment->delete();
        return response()->json(['res' => true, 'msg' => 'Comentario eliminado'], 200);
    }

    // FUNCION PARA ACTUALIZAR COMENTARIO
    public function update(int $id, commentRequest $request)
    {
        $resp=['res' => false, 'msg' => 'No autorizado'];
        $status_resp = 401;
        // BUSCA GRUPO DEL USUARIO
        $user_token = $request->user();
        if (!$user_token) {
            return response()->json($resp, $status_resp);
        }
        $comment = Comment::find($id);
        $resp=['res' => false, 'msg' => 'Comentario no existe'];
        if ( !$comment ) {
            return response()->json($resp, $status_resp);
        }
        if ( $user_token->id != $comment->user_id ) {
            return response()->json($resp, $status_resp);
        }
        $comment->comment = $request->txt;
        $comment->save();
        return response()->json(['res' => true, 'msg' => 'Comentario actualizado'], 200);
    }
}