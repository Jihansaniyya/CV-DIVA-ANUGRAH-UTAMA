<?php

namespace App\Http\Controllers\Api;

use App\Enums\ReportType;
use App\Exports\FinalReportExport;
use App\Exports\MonthlyReportExport;
use App\Exports\WeeklyReportExport;
use App\Http\Controllers\Controller;
use App\Http\Resources\ReportDocumentResource;
use App\Models\Period;
use App\Models\Project;
use App\Models\ReportDocument;
use App\Services\ReportDocumentService;
use App\Services\ReportService;
use App\Services\ZonaWaktu;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public function __construct(
        private readonly ReportService $reports,
        private readonly ReportDocumentService $documents,
    ) {}

    public function weekly(Request $request): JsonResponse
    {
        [$project, $period] = $this->konteksMingguan($request);

        return response()->json(['data' => $this->reports->weekly($project, $period)]);
    }

    public function monthly(Request $request): JsonResponse
    {
        $project = $this->project($request);
        $bulanKe = $request->integer('bulan_ke', 1);

        return response()->json(['data' => $this->reports->monthly($project, $bulanKe)]);
    }

    /** Laporan akhir: rekap kondisi proyek sampai laporan progres terakhir. */
    public function final(Request $request): JsonResponse
    {
        $project = $this->project($request);

        return response()->json(['data' => $this->reports->final($project)]);
    }

    /** Daftar dokumen laporan yang pernah digenerate. */
    public function documents(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $request->validate(['tipe' => ['sometimes', Rule::enum(ReportType::class)]], [
            'tipe.*' => 'Jenis laporan hanya Mingguan, Bulanan, atau Akhir.',
        ]);

        // Berpaginasi (bukan dipotong 50 data) agar dokumen lama tetap dapat ditelusuri per halaman.
        $documents = ReportDocument::with(['project', 'user'])
            ->whereHas('project', fn ($q) => $q->visibleTo($user))
            ->when($request->filled('project_id'), fn ($q) => $q->where('project_id', $request->integer('project_id')))
            ->when($request->filled('tipe'), fn ($q) => $q->where('tipe_laporan', $request->string('tipe')))
            ->orderByDesc('digenerate_pada')
            ->orderByDesc('id')
            ->paginate($this->perPage($request, 20))
            ->withQueryString();

        return ReportDocumentResource::collection($documents);
    }

    /** Export laporan ke Excel (.xlsx) memakai Laravel Excel. */
    public function exportExcel(Request $request): JsonResponse
    {
        $request->validate(['tipe' => ['required', 'in:MINGGUAN,BULANAN']], [
            'tipe.in' => 'Jenis laporan hanya Mingguan atau Bulanan.',
        ]);

        $tipe = $request->string('tipe')->toString();
        [$project, $data, $periodeMulai, $periodeSelesai, $label] = $this->dataLaporan($request, $tipe);

        $export = $tipe === 'MINGGUAN' ? new WeeklyReportExport($data) : new MonthlyReportExport($data);

        $namaFile = $this->namaFile($project, $tipe, $label, 'xlsx');
        $relatif = 'reports/'.$project->id.'/'.$namaFile;

        Excel::store($export, $relatif, 'public');

        return $this->responseDokumen($request, $project, $tipe, 'EXCEL', $relatif, $namaFile, $periodeMulai, $periodeSelesai);
    }

    /** Export laporan akhir ke Excel (.xlsx). */
    public function exportFinalExcel(Request $request): JsonResponse
    {
        $project = $this->project($request);
        $data = $this->reports->final($project);

        abort_if(
            $data['laporan_terakhir'] === null,
            422,
            'Laporan Akhir belum dapat dibuat karena belum ada laporan progres yang dikirim.',
        );

        // Setiap foto diperkecil sebelum disisipkan; proyek dengan ratusan foto bisa melewati batas 30 detik bawaan PHP.
        if (function_exists('set_time_limit')) {
            @set_time_limit(300);
        }

        $tipe = ReportType::AKHIR->value;
        $namaFile = $this->namaFile($project, $tipe, 's-d-'.$data['laporan_terakhir']['nama_periode'], 'xlsx');
        $relatif = 'reports/'.$project->id.'/'.$namaFile;

        Excel::store(new FinalReportExport($data), $relatif, 'public');

        return $this->responseDokumen(
            $request, $project, $tipe, 'EXCEL', $relatif, $namaFile,
            $data['header']['tanggal_mulai'], $data['laporan_terakhir']['tanggal_laporan'],
        );
    }

    /** @return array{0:Project,1:array,2:?string,3:?string,4:string} */
    private function dataLaporan(Request $request, string $tipe): array
    {
        if ($tipe === 'MINGGUAN') {
            [$project, $period] = $this->konteksMingguan($request);

            return [
                $project,
                $this->reports->weekly($project, $period),
                $period->tanggal_mulai->toDateString(),
                $period->tanggal_selesai->toDateString(),
                'minggu-'.$period->minggu_ke,
            ];
        }

        $project = $this->project($request);
        $bulanKe = $request->integer('bulan_ke', 1);
        $data = $this->reports->monthly($project, $bulanKe);

        return [$project, $data, $data['periode']['tanggal_mulai'], $data['periode']['tanggal_selesai'], 'bulan-'.$bulanKe];
    }

    /** @return array{0:Project,1:Period} */
    private function konteksMingguan(Request $request): array
    {
        $project = $this->project($request);

        $period = $request->filled('period_id')
            ? $project->periods()->findOrFail($request->integer('period_id'))
            : $project->periods()->orderBy('urutan')->first();

        abort_if($period === null, 422, 'Proyek belum memiliki periode pelaksanaan.');

        return [$project, $period];
    }

    private function project(Request $request): Project
    {
        $request->validate(['project_id' => ['required', 'exists:projects,id']]);

        $project = Project::findOrFail($request->integer('project_id'));
        $this->authorize('view', $project);

        return $project;
    }

    /** Contoh: laporan-mingguan-pembangunan-drainase-rt-11-minggu-2-20261009143000.xlsx */
    private function namaFile(Project $project, string $tipe, string $label, string $ekstensi): string
    {
        return Str::slug('laporan '.strtolower($tipe).' '.$project->nama_proyek.' '.$label).'-'.ZonaWaktu::sekarang()->format('YmdHis').'.'.$ekstensi;
    }

    private function responseDokumen(
        Request $request,
        Project $project,
        string $tipe,
        string $format,
        string $relatif,
        string $namaFile,
        ?string $periodeMulai,
        ?string $periodeSelesai,
    ): JsonResponse {
        $dokumen = $this->documents->catat(
            $project, $request->user(), ReportType::from($tipe)->value, $format, $relatif, $namaFile, $periodeMulai, $periodeSelesai,
        );

        return response()->json([
            'message' => 'Laporan berhasil dibuat.',
            'data' => new ReportDocumentResource($dokumen->load(['project', 'user'])),
        ], 201);
    }
}
