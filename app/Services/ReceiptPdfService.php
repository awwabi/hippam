<?php

namespace App\Services;

use App\Models\Pembayaran;
use App\Models\Tagihan;
use App\Models\Tenant;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class ReceiptPdfService
{
    private const PAPER_WIDTHS = [
        '58mm' => 164.5,
        '80mm' => 227,
    ];

    public function generateReceipt(Pembayaran $pembayaran, Tenant $tenant)
    {
        $width = self::PAPER_WIDTHS[$tenant->printer_width] ?? 164.5;

        $tagihan = $pembayaran->tagihan;
        $pelanggan = $tagihan->pelanggan;
        $pembacaan = $tagihan->pembacaan;
        $meter = $pembacaan->meter ?? null;

        $data = [
            'tenant' => $tenant,
            'pembayaran' => $pembayaran,
            'tagihan' => $tagihan,
            'pelanggan' => $pelanggan,
            'pembacaan' => $pembacaan,
            'meter' => $meter,
            'isInvoice' => false,
            'statusLabel' => $this->getStatusLabel($tagihan),
            'printedAt' => \Carbon\Carbon::now()->format('j/n/Y H.i.s'),
            'periodeLabel' => $this->formatPeriode($tagihan->periode),
        ];

        $pdf = Pdf::loadView('prints.receipt', $data)
            ->setPaper([0, 0, $width, 800], 'portrait')
            ->setOption(['dpi' => 72, 'defaultFont' => 'Courier']);

        $dompdf = $pdf->getDomPDF();
        $dompdf->render();
        $height = $dompdf->get_canvas()->get_height() + 20;

        $pdf = Pdf::loadView('prints.receipt', $data)
            ->setPaper([0, 0, $width, $height], 'portrait')
            ->setOption(['dpi' => 72, 'defaultFont' => 'Courier']);

        return $pdf->download("kwitansi-{$pembayaran->id}.pdf");
    }

    public function generateInvoice(Tagihan $tagihan, Tenant $tenant)
    {
        $width = self::PAPER_WIDTHS[$tenant->printer_width] ?? 164.5;

        $pelanggan = $tagihan->pelanggan;
        $pembacaan = $tagihan->pembacaan;
        $meter = $pembacaan->meter ?? null;

        $data = [
            'tenant' => $tenant,
            'pembayaran' => null,
            'tagihan' => $tagihan,
            'pelanggan' => $pelanggan,
            'pembacaan' => $pembacaan,
            'meter' => $meter,
            'isInvoice' => true,
            'statusLabel' => 'BELUM BAYAR',
            'printedAt' => \Carbon\Carbon::now()->format('j/n/Y H.i.s'),
            'periodeLabel' => $this->formatPeriode($tagihan->periode),
        ];

        $pdf = Pdf::loadView('prints.receipt', $data)
            ->setPaper([0, 0, $width, 800], 'portrait')
            ->setOption(['dpi' => 72, 'defaultFont' => 'Courier']);

        $dompdf = $pdf->getDomPDF();
        $dompdf->render();
        $height = $dompdf->get_canvas()->get_height() + 20;

        $pdf = Pdf::loadView('prints.receipt', $data)
            ->setPaper([0, 0, $width, $height], 'portrait')
            ->setOption(['dpi' => 72, 'defaultFont' => 'Courier']);

        return $pdf->download("invoice-{$tagihan->id}.pdf");
    }

    private function getStatusLabel(Tagihan $tagihan): string
    {
        return match ($tagihan->status) {
            'lunas' => 'LUNAS',
            'cicilan' => 'CICILAN',
            'belum_bayar' => 'BELUM BAYAR',
            default => strtoupper($tagihan->status),
        };
    }

    private function formatPeriode(string $periode): string
    {
        $months = [
            '01' => 'Januari', '02' => 'Februari', '03' => 'Maret',
            '04' => 'April', '05' => 'Mei', '06' => 'Juni',
            '07' => 'Juli', '08' => 'Agustus', '09' => 'September',
            '10' => 'Oktober', '11' => 'November', '12' => 'Desember',
        ];

        [$year, $month] = explode('-', $periode);

        return ($months[$month] ?? $month) . ' ' . $year;
    }
}
