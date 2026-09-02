<?php

namespace App\Http\Controllers\comunidad;

use App\Http\Controllers\Controller;
use App\Http\Requests\comunidad\savePublishRequest;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\Request;

class publicacionController extends Controller
{
    // FUNCION PARA GENERAR USUARIO QUE APRUEBA PRESTAMOS
    public function index(Request $request)
    {
        $resp=['res' => false, 'msg' => 'Usuario no autenticado'];
        $status_resp = 401;
        // BUSCA GRUPO DEL USUARIO
        $user_token = $request->user();
        if (!$user_token) {
            return response()->json($resp, $status_resp);
        }

        $posts = Post::where([['grupo_id', $user_token->grupo_id],['activo', 1]])
            ->orderBy('created_at', 'desc')
            // ->pagination($request->per_page, $request->page)
            ->get();

        $arr_post = [];
        foreach ($posts as $post) {
            $coments = $post->comments()->where('activo', 1)->get();
            // var_dump($coments);
            $arr_comments = [];
            foreach ($coments as $comment) {
                array_push($arr_comments, [
                    'nombre' => $comment->user->nombre . ' ' . $comment->user->apellido_p . ' ' . $comment->user->apellido_m,
                    'comentario' => $comment->comment,
                    'fecha' => $comment->created_at->format('d/m/Y'),
                    'hora' => $comment->created_at->format('H:i')
                ]);
            }
            array_push($arr_post, [
                'id' => $post->id,
                'post' => $post->post,
                'image' => $post->image,
                'user' => $post->user->nombre . ' ' . $post->user->apellido_p . ' ' . $post->user->apellido_m,
                'fecha' => $post->created_at->format('d/m/Y'),
                'hora' => $post->created_at->format('H:i'),
                'comentarios' => $arr_comments
            ]);
        }
        // BUSCA LA INFO DE EL GRUPO
        $grupo = $user_token->grupo->group_name;
        $img = $user_token->grupo->img_group;

        return response()->json([
            'res' => true,
            'grupo' => $grupo,
            'img_grupo' => public_path('/img/group/'.$img),
            'publicaciones' => $arr_post
        ], 200);
    }

    // FUNCION PARA GUARDAR LA PUBLICACIÓN
    public function store(savePublishRequest $request) {
        $resp=['res' => false, 'msg' => 'Usuario no autenticado'];
        $status_resp = 401;
        // BUSCA GRUPO DEL USUARIO
        $user_token = $request->user();
        if (!$user_token) {
            return response()->json($resp, $status_resp);
        }

        $post = new Post();
        $fileName = time() . '_' . $user_token->id . '.png';
        if ( $request->img != null ) {
            arch_adjunto_publish($request->img, $fileName);
            $post->image = $fileName;
        }
        $post->post = $request->txt;
        $post->user_id = $user_token->id;
        $post->grupo_id = $user_token->grupo_id;
        $post->activo = 1;
        try {
            $resp['res'] = true;
            $resp['msg'] = 'Publicación guardada correctamente';
            $status_resp = 200;
            $post->save();
        } catch (\Throwable $th) {
            $resp['res'] = false;
            $resp['msg'] = $th->getMessage();
            $status_resp = 409;
        }
        return response()->json($resp, $status_resp);
    }

    // FUNCIÓN ELIMINA PUBLICACIÓN
    public function destroy(Request $request, $id) {
        $resp=['res' => false, 'msg' => 'No se elimino la publicación'];
        $status_resp = 401;
        // BUSCA GRUPO DEL USUARIO
        $user_token = $request->user();
        if (!$user_token) {
            return response()->json($resp, $status_resp);
        }
        $post = Post::find($id);
        if ($post->user_id != $user_token->id) {
            $resp['msg'] = 'No tienes permiso para eliminar esta publicación';
            return response()->json($resp, 401);
        }
        try {
            $post->delete();
            $resp['res'] = true;
            $resp['msg'] = 'Publicación eliminada correctamente';
            $status_resp = 200;
        } catch (\Throwable $th) {
            $resp['res'] = false;
            $resp['msg'] = $th->getMessage();
        }
        return response()->json($resp, $status_resp);
    }
    // GENERA UNA ACTUALIZACIÓN EN PUBLICACIONES
    public function update(savePublishRequest $request, $id) {
        $resp=['res' => false, 'msg' => 'No se actualizó la publicación'];
        $status_resp = 401;
        // BUSCA GRUPO DEL USUARIO
        $user_token = $request->user();
        if (!$user_token) {
            return response()->json($resp, $status_resp);
        }
        $post = Post::find($id);
        if ($post->user_id != $user_token->id) {
            $resp['msg'] = 'No tienes permiso para editar esta publicación';
            return response()->json($resp, 401);
        }
        try {
            $post->post = $request->txt;
            $fileName = time() . '_' . $user_token->id . '.png';
            if ( $request->img != null ) {
                arch_adjunto_publish($request->img, $fileName);
                $post->image = $fileName;
            }
            $post->update();
            $resp['res'] = true;
            $resp['msg'] = 'Publicación actualizada correctamente';
            $status_resp = 200;
        } catch (\Throwable $th) {
            $resp['res'] = false;
            $resp['msg'] = $th->getMessage();
        }
        return response()->json($resp, $status_resp);
    }

}