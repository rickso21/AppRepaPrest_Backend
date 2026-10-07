<?php

namespace App\Http\Controllers\comunidad;

use App\Events\CommentCreated;
use App\Http\Controllers\Controller;
use App\Http\Requests\comunidad\commentRequest;
use App\Models\Comment;
use App\Models\Post;
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

        if (!$this->usuarioPuedeAccederAlGrupo($user_token, (int) $post->group_id)) {
            return response()->json([
                'res' => false,
                'msg' => 'No tienes permiso para comentar en esta publicación',
            ], 403);
        }

        // Guardar audio (si viene) ANTES de crear el comentario
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
                'audio'   => $audioPath,
            ]);

            // ✅ Pasar group_id explícito para no hacer query extra en el Event
            broadcast(new CommentCreated($comment, (int) $post->group_id))->toOthers();

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
                    'audio'      => $comment->audio,
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

        // Opcional: emitir CommentDeleted aquí si quieres sync en tiempo real
        // broadcast(new \App\Events\CommentDeleted($comment))->toOthers();

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

        // Opcional: emitir CommentUpdated aquí si quieres sync en tiempo real

        return response()->json([
            'res' => true,
            'msg' => 'Comentario actualizado',
        ], 200);
    }

    // ================================================================
    // HELPERS PRIVADOS
    // ================================================================

    private function guardarArchivo($file, string $carpeta, string $baseName): string
    {
        $ext     = strtolower($file->getClientOriginalExtension() ?: 'bin');
        $name    = $baseName . '.' . $ext;
        $destino = public_path($carpeta);

        if (!file_exists($destino)) {
            mkdir($destino, 0775, true);
        }

        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $this->comprimirImagen($file->getRealPath(), $destino . '/' . $name, $ext);
        } else {
            $file->move($destino, $name);
        }

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

    private function usuarioPuedeAccederAlGrupo($user, int $grupoId): bool
    {
        if (!$user->grupo_id) return false;

        $visibles = \App\Models\Grupo::gruposVisiblesDe((int) $user->grupo_id);

        return in_array($grupoId, $visibles, true);
    }
}
