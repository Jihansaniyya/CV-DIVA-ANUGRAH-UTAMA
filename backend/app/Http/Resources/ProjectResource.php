<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nama_proyek' => $this->nama_proyek,
            'nomor_spk' => $this->nomor_spk,
            'nomor_pekerjaan' => $this->nomor_pekerjaan,
            'nomor_proyek' => $this->nomor_proyek,
            'lokasi' => $this->lokasi,
            'sumber_dana' => $this->sumber_dana,
            'tahun_anggaran' => $this->tahun_anggaran,
            'tanggal_spk' => $this->tanggal_spk?->toDateString(),
            'tanggal_mulai' => $this->tanggal_mulai?->toDateString(),
            'tanggal_selesai' => $this->tanggal_selesai?->toDateString(),
            'jangka_waktu_hari' => $this->jangka_waktu_hari,
            'kontraktor_pelaksana' => $this->kontraktor_pelaksana,
            'konsultan_pengawas' => $this->konsultan_pengawas,
            'nama_site_engineer' => $this->nama_site_engineer,
            'nama_pelaksana_lapangan' => $this->nama_pelaksana_lapangan,
            'qs_user_id' => $this->qs_user_id,
            'qs' => new UserResource($this->whenLoaded('qs')),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'keterangan' => $this->keterangan,
            'jumlah_pekerjaan' => $this->whenCounted('workItems'),
            'jumlah_periode' => $this->whenCounted('periods'),
            'progres_aktual' => $this->when(isset($this->progres_aktual), fn () => (float) $this->progres_aktual),
            'progres_rencana' => $this->when(isset($this->progres_rencana), fn () => (float) $this->progres_rencana),
            'deviasi' => $this->when(isset($this->deviasi), fn () => (float) $this->deviasi),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
