<?php

namespace App\Http\Controllers\Api;

use App\Enums\ReportType;
use App\Exports\DailyReportExport;
use App\Exports\MonthlyReportExport;
use App\Exports\WeeklyReportExport;
use App\Http\Controllers\Controller;
use App\Http\Resources\ReportDocumentResource;
use App\Models\Period;
use App\Models\Project;
use App\Models\ReportDocument;
use App\Services\ReportService;
use App\Services\WordExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public function __construct(
        private readonly ReportService $reports,
        private readonly WordExportService $word,
    ) {}

    public function daily(Request $request): JsonResponse
    {
        [$project, $data] = $this->dataHarian($request);

        return response()->json(['data' => $data]);
    }

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

    public function milestone(Request $request): JsonResponse
    {
        $project = $this->project($request);

        return response()->json(['data' => $this->reports->milestone($project)]);
    }

    /** Daftar dokumen laporan yang pernah digenerate. */
    public function documents(Request $request): JsonResponse
    {
        $user = $request->user();

        $documents = ReportDocument::with(['project', 'user'])
            ->whereHas('project', fn ($q) => $q->visibleTo($user))
            ->when($request->filled('project_id'), fn ($q) => $q->where('project_id', $request->integer('project_id')))
            ->orderByDesc('digenerate_pada')
            ->limit(50)
            ->get();

        return response()->json(['data' => ReportDocumentResource::collection($documents)]);
    }

    /** Export laporan ke Excel (.xlsx) memakai Laravel Excel. */
    public function exportExcel(Request $request): JsonResponse
    {
        $request->validate(['tipe' => ['required', 'in:HARIAN,MINGGUAN,BULANAN']]);

        $tipe = $request->string('tipe')->toString();
        [$project, $data, $periodeMulai, $periodeSelesai, $label] = $this->dataLaporan($request, $tipe);

        $export = match ($tipe) {
            'MINGGUAN' => new WeeklyReportExport($data),
            'BULANAN' => new MonthlyReportExport($data),
            default => new DailyReportExport($data),
        };

        $namaFile = $this->namaFile($project, $tipe, $label, 'xlsx');
        $relatif = 'reports/'.$project->id.'/'.$namaFile;

        Excel::store($export, $relatif, 'public');

        return $this->responseDokumen($request, $project, $tipe, 'EXCEL', $relatif, $namaFile, $periodeMulai, $periodeSelesai);
    }

    /** Export laporan ke Word (.docx) memakai PHPWord. */
    public function exportWord(Request $request): JsonResponse
    {
        $request->validate(['tipe' => ['required', 'in:HARIAN,MINGGUAN,BULANAN']]);

        $tipe = $request->string('tipe')->toString();
        [$project, $data, $periodeMulai, $periodeSelesai, $label] = $this->dataLaporan($request, $tipe);

        $namaFile = $this->namaFile($project, $tipe, $label, 'docx');
        $relatif = 'reports/'.$project->id.'/'.$namaFile;
        $absolut = storage_path('app/public/'.$relatif);

        match ($tipe) {
            'MINGGUAN' => $this->word->weekly($data, $absolut),
            'BULANAN' => $this->word->monthly($data, $absolut),
            default => $this->word->daily($data, $absolut),
        };

        return $this->responseDokumen($request, $project, $tipe, 'WORD', $relatif, $namaFile, $periodeMulai, $periodeSelesai);
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

        if ($tipe === 'BULANAN') {
            $project = $this->project($request);
            $bulanKe = $request->integer('bulan_ke', 1);
            $data = $this->reports->monthly($project, $bulanKe);

            return [$project, $data, $data['periode']['tanggal_mulai'], $data['periode']['tanggal_selesai'], 'bulan-'.$bulanKe];
        }

        [$project, $data, $dari, $sampai] = $this->dataHarian($request);

        return [$project, $data, $dari, $sampai, 'harian'];
    }

    /** @return array{0:Project,1:array,2:string,3:string} */
    private function dataHarian(Request $request): array
    {
        $project = $this->project($request);
        $dari = $request->string('dari')->toString() ?: $project->tanggal_mulai->toDateString();
        $sampai = $request->string('sampai')->toString() ?: $project->tanggal_selesai->toDateString();

        return [$project, $this->reports->daily($project, $dari, $sampai), $dari, $sampai];
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

    private function namaFile(Project $project, string $tipe, string $label, string $ekstensi): string
    {
        return Str::slug($project->nama_proyek.' laporan '.strtolower($tipe).' '.$label).'-'.now()->format('YmdHis').'.'.$ekstensi;
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
        $dokumen = ReportDocument::create([
            'project_id' => $project->id,
            'user_id' => $request->user()->id,
            'tipe_laporan' => ReportType::from($tipe)->value,
            'format' => $format,
            'periode_mulai' => $periodeMulai,
            'periode_selesai' => $periodeSelesai,
            'file_path' => $relatif,
            'file_name' => $namaFile,
            'digenerate_pada' => now(),
        ]);

        return response()->json([
            'message' => 'Laporan berhasil dibuat.',
            'data' => new ReportDocumentResource($dokumen->load(['project', 'user'])),
        ], 201);
    }
}
