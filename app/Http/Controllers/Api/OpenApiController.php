<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\WebhookEndpoint;

/** OpenAPI 3.1 for the public API, built from the same constants the code uses (scopes, events). */
class OpenApiController extends Controller
{
    public function __invoke()
    {
        $err = ['description' => 'Error', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']]]];
        $page = fn (string $item) => ['type' => 'object', 'properties' => ['data' => ['type' => 'array', 'items' => ['$ref' => "#/components/schemas/$item"]], 'next_cursor' => ['type' => ['string', 'null'], 'description' => 'Pass as ?cursor= for the next page']]];
        $one = fn (string $item) => ['type' => 'object', 'properties' => ['data' => ['$ref' => "#/components/schemas/$item"]]];
        $json = fn ($schema, string $d = 'OK') => ['description' => $d, 'content' => ['application/json' => ['schema' => $schema]]];
        $q = fn (string $name, string $desc, array $schema = ['type' => 'string']) => ['name' => $name, 'in' => 'query', 'required' => false, 'description' => $desc, 'schema' => $schema];
        $code = ['name' => 'code', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string'], 'example' => 'EMP0001'];
        $idem = ['name' => 'Idempotency-Key', 'in' => 'header', 'required' => false, 'description' => 'Retry-safe POST/PATCH: same key + same body replays the first response', 'schema' => ['type' => 'string']];
        $cursor = [$q('cursor', 'Cursor from next_cursor'), $q('limit', '1–500, default 100', ['type' => 'integer'])];
        $range = [$q('from', 'Date (IST), default today', ['type' => 'string', 'format' => 'date']), $q('to', 'Date (IST), default = from; max 93 days', ['type' => 'string', 'format' => 'date']),
            $q('office_code', 'e.g. HYD01'), $q('employee_code', 'e.g. EMP0001'), $q('type', 'IN or OUT', ['type' => 'string', 'enum' => ['IN', 'OUT']])];
        $op = fn (string $scope, string $summary, array $responses, array $params = [], ?array $body = null) => array_filter([
            'summary' => $summary, 'security' => [['apiKey' => [$scope]]], 'x-scope' => $scope, 'parameters' => $params ?: null,
            'requestBody' => $body ? ['required' => true, 'content' => ['application/json' => ['schema' => $body]]] : null,
            'responses' => $responses + ['401' => $err, '403' => $err, '429' => $err],
        ]);
        $empIn = ['$ref' => '#/components/schemas/EmployeeInput'];

        return response()->json([
            'openapi' => '3.1.0',
            'info' => ['title' => 'QRONEX API', 'version' => '1.0.0', 'description' => "Read verified attendance and keep employees in sync.\n\n**No endpoint creates punches** — attendance only comes from the QRONEX app after server-side checks.\n\nRate limit: 120 requests/min per key (429 + Retry-After). Times are ISO-8601 UTC; `from`/`to` dates are IST."],
            'servers' => [['url' => url('/api/v1')]],
            'components' => [
                'securitySchemes' => ['apiKey' => ['type' => 'http', 'scheme' => 'bearer', 'description' => 'Company API key `qrx_live_…` from Portal → API & webhooks. Scopes: '.implode(', ', array_keys(ApiKey::SCOPES))]],
                'schemas' => [
                    'Error' => ['type' => 'object', 'properties' => ['error' => ['type' => 'object', 'properties' => ['code' => ['type' => 'string'], 'message' => ['type' => 'string']]]]],
                    'Office' => ['type' => 'object', 'properties' => ['office_code' => ['type' => 'string'], 'name' => ['type' => 'string'], 'slug' => ['type' => 'string'], 'lat' => ['type' => 'number'], 'lng' => ['type' => 'number'],
                        'geofence_radius_m' => ['type' => 'integer'], 'timezone' => ['type' => 'string'], 'is_active' => ['type' => 'boolean'],
                        'gates' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => ['gate_code' => ['type' => 'string'], 'name' => ['type' => 'string'], 'is_active' => ['type' => 'boolean']]]]]],
                    'Employee' => ['type' => 'object', 'properties' => ['employee_code' => ['type' => 'string'], 'name' => ['type' => 'string'], 'email' => ['type' => ['string', 'null']], 'mobile' => ['type' => ['string', 'null']],
                        'department' => ['type' => ['string', 'null']], 'designation' => ['type' => ['string', 'null']], 'hrms_ref' => ['type' => ['string', 'null']], 'office_code' => ['type' => 'string'],
                        'status' => ['type' => 'string', 'enum' => ['active', 'inactive']], 'updated_at' => ['type' => 'string', 'format' => 'date-time']]],
                    'EmployeeInput' => ['type' => 'object', 'properties' => ['employee_code' => ['type' => 'string', 'description' => 'Create only'], 'name' => ['type' => 'string'], 'office_code' => ['type' => 'string'],
                        'email' => ['type' => 'string'], 'mobile' => ['type' => 'string'], 'department' => ['type' => 'string'], 'designation' => ['type' => 'string'], 'hrms_ref' => ['type' => 'string']]],
                    'AttendanceEvent' => ['type' => 'object', 'properties' => ['id' => ['type' => 'string', 'example' => 'QRX-00001289'], 'type' => ['type' => 'string', 'enum' => ['IN', 'OUT']], 'mode' => ['type' => 'string', 'example' => 'OFFICE_QR'],
                        'recorded_at' => ['type' => 'string', 'format' => 'date-time'], 'employee_code' => ['type' => 'string'], 'employee_name' => ['type' => 'string'], 'hrms_ref' => ['type' => ['string', 'null']],
                        'office_code' => ['type' => 'string'], 'gate_code' => ['type' => 'string'], 'location' => ['type' => 'object', 'properties' => ['distance_m' => ['type' => 'integer'], 'accuracy_m' => ['type' => 'integer']]]]],
                    'Webhook' => ['type' => 'object', 'properties' => ['id' => ['type' => 'integer'], 'url' => ['type' => 'string'], 'description' => ['type' => ['string', 'null']], 'events' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => array_keys(WebhookEndpoint::EVENTS)]],
                        'is_active' => ['type' => 'boolean'], 'secret' => ['type' => 'string', 'description' => 'Only returned on create']]],
                ],
            ],
            'paths' => [
                '/offices' => ['get' => $op('offices:read', 'List offices and gates', ['200' => $json(['type' => 'object', 'properties' => ['data' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Office']]]])])],
                '/employees' => [
                    'get' => $op('employees:read', 'List employees (cursor paginated)', ['200' => $json($page('Employee'))], [...$cursor, $q('office_code', 'Filter'), $q('status', 'active|inactive'), $q('updated_since', 'ISO date-time — for incremental sync', ['type' => 'string', 'format' => 'date-time'])]),
                    'post' => $op('employees:write', 'Create an employee', ['201' => $json($one('Employee'), 'Created'), '422' => $err], [$idem], $empIn),
                ],
                '/employees/{code}' => [
                    'get' => $op('employees:read', 'Get one employee', ['200' => $json($one('Employee')), '404' => $err], [$code]),
                    'patch' => $op('employees:write', 'Update employee details or office', ['200' => $json($one('Employee')), '404' => $err, '422' => $err], [$code, $idem], $empIn),
                ],
                '/employees/{code}/deactivate' => ['post' => $op('employees:write', 'Deactivate an employee and revoke their phones', ['200' => $json($one('Employee')), '404' => $err], [$code, $idem])],
                '/attendance/events' => ['get' => $op('attendance:read', 'List verified IN/OUT events (cursor paginated, oldest first)', ['200' => $json($page('AttendanceEvent')), '422' => $err], [...$range, ...$cursor])],
                '/attendance/events/{ref}' => ['get' => $op('attendance:read', 'Get one event', ['200' => $json($one('AttendanceEvent')), '404' => $err], [['name' => 'ref', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']]])],
                '/attendance/export' => ['get' => $op('attendance:export', 'Download events as CSV', ['200' => ['description' => 'CSV', 'content' => ['text/csv' => ['schema' => ['type' => 'string']]]]], $range)],
                '/remote-permissions' => [
                    'get' => $op('remote:write', 'List remote / field permissions', ['200' => $json(['type' => 'object'])], [...$cursor, $q('status', 'pending|approved|rejected|revoked')]),
                    'post' => $op('remote:write', 'Grant an approved remote permission (WFH, field duty, client site)', ['201' => $json(['type' => 'object'], 'Created'), '404' => $err, '422' => $err], [$idem],
                        ['type' => 'object', 'required' => ['employee_code', 'type', 'from', 'to', 'reason'], 'properties' => [
                            'employee_code' => ['type' => 'string'], 'type' => ['type' => 'string', 'enum' => array_keys(\App\Models\RemotePermission::TYPES)],
                            'from' => ['type' => 'string', 'format' => 'date'], 'to' => ['type' => 'string', 'format' => 'date'], 'reason' => ['type' => 'string'],
                            'location_waived' => ['type' => 'boolean'], 'location_label' => ['type' => 'string'], 'lat' => ['type' => 'number'], 'lng' => ['type' => 'number'], 'radius_m' => ['type' => 'integer']]]),
                ],
                '/webhooks' => [
                    'get' => $op('webhooks:manage', 'List webhook endpoints', ['200' => $json(['type' => 'object', 'properties' => ['data' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Webhook']]]])]),
                    'post' => $op('webhooks:manage', 'Create a webhook endpoint (HTTPS, public address)', ['201' => $json($one('Webhook'), 'Created'), '422' => $err], [$idem],
                        ['type' => 'object', 'required' => ['url', 'events'], 'properties' => ['url' => ['type' => 'string'], 'description' => ['type' => 'string'], 'events' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => array_keys(WebhookEndpoint::EVENTS)]]]]),
                ],
                '/webhooks/{id}' => ['delete' => $op('webhooks:manage', 'Delete a webhook endpoint', ['204' => ['description' => 'Deleted'], '404' => $err], [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]])],
            ],
            'webhooks' => collect(WebhookEndpoint::EVENTS)->mapWithKeys(fn ($desc, $event) => [$event => ['post' => [
                'summary' => $desc,
                'description' => "Headers: X-Qronex-Event, X-Qronex-Delivery, X-Qronex-Signature: t=<unix>,v1=<hex HMAC-SHA256(secret, \"<t>.<raw body>\")>. Reply 2xx within 10 s; failures retry with backoff for ~24 h. Deduplicate on body.id.",
                'requestBody' => ['content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['id' => ['type' => 'string'], 'event' => ['type' => 'string', 'const' => $event], 'created_at' => ['type' => 'string'], 'tenant' => ['type' => 'string'],
                    'data' => in_array($event, ['attendance.created', 'attendance.adjusted'], true) ? ['$ref' => '#/components/schemas/AttendanceEvent'] : ($event === 'employee.deactivated' ? ['$ref' => '#/components/schemas/Employee'] : ['type' => 'object'])]]]]],
                'responses' => ['200' => ['description' => 'Acknowledged']],
            ]]])->all(),
        ]);
    }
}
