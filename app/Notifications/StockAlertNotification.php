<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/** Roadmap 13c: low-stock alert for one business (in-app bell + email). business_id is stored so the bell filters by business. */
class StockAlertNotification extends Notification
{
    use Queueable;

    /** @param list<array{product_id:int, name:string, stock:float, alert_qty:float, unit:?string}> $alerts */
    public function __construct(public int $businessId, public string $businessName, public ?string $invoiceNo, public array $alerts)
    {
    }

    public function via(object $notifiable): array
    {
        // The email goes through the business's own account (NotifyStockAlert → SendBusinessMail), not the platform mailer.
        return ['database'];
    }

    private function title(): string
    {
        return count($this->alerts) === 1 ? "Low stock: {$this->alerts[0]['name']}" : count($this->alerts).' products are low on stock';
    }

    private function line(array $a): string
    {
        $unit = $a['unit'] ? ' '.$a['unit'] : '';

        return "{$a['name']}: {$a['stock']}{$unit} left (alert at {$a['alert_qty']}{$unit})";
    }

    /** Lines of the email body (also used by the in-app bell). */
    public function emailLines(): array
    {
        return array_merge([$this->invoiceNo ? "After sale {$this->invoiceNo}:" : 'After the last sale:'],
            array_map(fn ($a) => '• '.$this->line($a), array_slice($this->alerts, 0, 20)));
    }

    public function subject(): string
    {
        return "{$this->businessName}: {$this->title()}";
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'stock_alert',
            'business_id' => $this->businessId,
            'title' => $this->title(),
            'lines' => array_map(fn ($a) => $this->line($a), array_slice($this->alerts, 0, 10)),
            'invoice_no' => $this->invoiceNo,
            'url' => route('reports.stock_indication', [], false),
        ];
    }
}
