<?php

namespace App\Services;

use App\Models\MessageLog;
use App\Models\MessageTemplate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Sends templated email/SMS and logs every attempt. Never throws: a failed message must not break the business action.
 * ponytail: sends inline; move to queued jobs (QUEUE_CONNECTION=database is already set) when volume grows.
 */
class Notifier
{
    /** @param list<array{name:string,data:string,mime:string}> $attachments */
    public static function email(string $key, ?string $to, array $vars = [], ?int $tenantId = null, array $attachments = []): void
    {
        if (! $to) {
            return;
        }
        $tpl = MessageTemplate::where(['channel' => 'email', 'key' => $key])->first();
        if (! $tpl || ! $tpl->enabled) {
            self::log($tenantId, 'email', $key, $to, null, 'skipped', $tpl ? 'Template disabled' : 'Template missing');

            return;
        }
        $subject = MessageTemplate::render($tpl->subject ?? 'QRONEX', $vars);
        try {
            Mail::raw(MessageTemplate::render($tpl->body, $vars), function ($m) use ($to, $subject, $attachments) {
                $m->to($to)->subject($subject);
                foreach ($attachments as $a) {
                    $m->attachData($a['data'], $a['name'], ['mime' => $a['mime']]);
                }
            });
            self::log($tenantId, 'email', $key, $to, $subject, 'sent');
        } catch (Throwable $e) {
            report($e);
            self::log($tenantId, 'email', $key, $to, $subject, 'failed', $e->getMessage());
        }
    }

    /** $vars order must match the {#var#} slots of the DLT-approved template. */
    public static function sms(string $key, ?string $mobile, array $vars = [], ?int $tenantId = null): void
    {
        if (! $mobile) {
            return;
        }
        $tpl = MessageTemplate::where(['channel' => 'sms', 'key' => $key])->first();
        if (! $tpl || ! $tpl->enabled) {
            self::log($tenantId, 'sms', $key, $mobile, null, 'skipped', $tpl ? 'Template disabled' : 'Template missing');

            return;
        }
        $text = MessageTemplate::render($tpl->body, $vars);
        $cfg = config('qronex.sms');

        if ($cfg['driver'] !== 'http') {
            self::log($tenantId, 'sms', $key, $mobile, $text, 'skipped', 'SMS_DRIVER=log (not sent)');

            return;
        }
        try {
            // ponytail: generic JSON payload — map field names to the chosen DLT gateway once it is selected.
            $res = Http::timeout(15)->post($cfg['url'], [
                'api_key' => $cfg['api_key'], 'sender' => $cfg['sender'], 'pe_id' => $cfg['pe_id'],
                'template_id' => $tpl->dlt_template_id, 'to' => preg_replace('/\D/', '', $mobile), 'message' => $text,
            ]);
            self::log($tenantId, 'sms', $key, $mobile, $text, $res->successful() ? 'sent' : 'failed', $res->successful() ? null : 'HTTP '.$res->status());
        } catch (Throwable $e) {
            report($e);
            self::log($tenantId, 'sms', $key, $mobile, $text, 'failed', $e->getMessage());
        }
    }

    private static function log(?int $tenantId, string $channel, string $key, string $to, ?string $subject, string $status, ?string $error = null): void
    {
        MessageLog::create([
            'tenant_id' => $tenantId, 'channel' => $channel, 'template_key' => $key, 'to' => $to,
            'subject' => $subject ? mb_substr($subject, 0, 250) : null, 'status' => $status, 'error' => $error ? mb_substr($error, 0, 250) : null,
        ]);
    }
}
