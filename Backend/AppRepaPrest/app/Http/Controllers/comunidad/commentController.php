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

        // Validar que el post esté en un grupo visible para el usuario
        if (!$this->usuarioPuedeAccederAlGrupo($user_token, (int) $post->group_id)) {
            return response()->json([
                'res' => false,
                'msg' => 'No tienes permiso para comentar en esta publicación',
            ], 403);
        }

        // 👇 Calcular la ruta del audio ANTES de crear el comentario
        $audioPath = null;

        if ($request->hasFile('audio')) {
            $audioPath = $this->guardarArchivo(
                $request->file('audio'),
                'audio/comments',
                time() . '_' . $user_token->id . '_c_audio'
            );
        }

        try {
            $comment = $post->comments()->create([
                'user_id' => $user_token->id,
                'post_id' => $post->id,
                'comment' => $request->comment ?? '',
                'activo'  => 1,
                'audio'   => $audioPath,        // 👈 usar la variable
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
                    'audio'      => $comment->audio,   // 👈 devolver audio
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

    // ================================================================
    // HELPERS PRIVADOS
    // ================================================================

    /**
     * Guarda un archivo en `public/{$carpeta}`.
     */
    private function guardarArchivo($file, string $carpeta, string $baseName): string
    {
        $ext  = $file->getClientOriginalExtension() ?: 'bin';
        $name = $baseName . '.' . $ext;
        $size = $file->getSize();
        $mime = $file->getMimeType();

        $path = public_path($carpeta);
        if (!file_exists($path)) {
            mkdir($path, 0775, true);
        }

        $file->move($path, $name);

        \Log::info('[comment] Archivo guardado', [
            'carpeta' => $carpeta,
            'file'    => $name,
            'size'    => $size,
            'mime'    => $mime,
        ]);

        return $name;
    }

    private function usuarioPuedeAccederAlGrupo($user, int $grupoId): bool
    {
        if (!$user->grupo_id) return false;

        $visibles = \App\Models\Grupo::gruposVisiblesDe((int) $user->grupo_id);

        return in_array($grupoId, $visibles, true);
    }
}
