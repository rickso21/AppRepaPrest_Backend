<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VoiceSignal extends Model
{
    protected $table = 'tbl_voice_signals';

    // ✅ AGREGAR timestamps para que use created_at y updated_at
    public $timestamps = true;  // Cambiar de false a true

    protected $fillable = [
        'from_user_id',
        'to_user_id',
        'type',
        'payload',
        'processed'
    ];

    protected $casts = [
        'payload' => 'array',
        'processed' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ✅ AGREGAR constantes para tipos de señales
    const TYPE_OFFER = 'offer';
    const TYPE_ANSWER = 'answer';
    const TYPE_ICE_CANDIDATE = 'ice-candidate';
    const TYPE_CALL_ENDED = 'call_ended';
    const TYPE_INCOMING_CALL = 'incoming_call';
    const TYPE_WALKIE_TALKIE = 'walkie_talkie';  // ✅ NUEVO

    // ✅ AGREGAR constantes para acciones de Walkie Talkie
    const WT_ACTION_TRANSMISSION_START = 'transmission_start';
    const WT_ACTION_TRANSMISSION_END = 'transmission_end';
    const WT_ACTION_AUDIO_CHUNK = 'audio_chunk';
    const WT_ACTION_USER_JOINED = 'user_joined';
    const WT_ACTION_USER_LEFT = 'user_left';

    public function fromUser()
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toUser()
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    // ✅ MÉTODO PARA VERIFICAR SI ES SEÑAL DE WALKIE TALKIE
    public function isWalkieTalkie(): bool
    {
        return $this->type === self::TYPE_WALKIE_TALKIE;
    }

    // ✅ MÉTODO PARA OBTENER LA ACCIÓN DEL PAYLOAD
    public function getWalkieTalkieAction(): ?string
    {
        if (!$this->isWalkieTalkie()) {
            return null;
        }
        return $this->payload['action'] ?? null;
    }

    // ✅ MÉTODO PARA VERIFICAR SI YA FUE PROCESADA
    public function markAsProcessed(): void
    {
        $this->processed = true;
        $this->save();
    }
}
