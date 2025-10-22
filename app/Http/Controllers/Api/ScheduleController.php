<?php
namespace App\Http\Controllers\Api;

use App\Integrations\Contracts\ScheduleProvider;
use Illuminate\Http\Request;

final class ScheduleController
{
    public function __construct(private ScheduleProvider $provider) {}

    public function index(Request $r)
    {
        $from = new \DateTimeImmutable($r->query('from', 'now'));
        $to   = new \DateTimeImmutable($r->query('to', '+1 day'));
        $group = $r->query('group', 'IPZ-11');
        return response()->json($this->provider->fetch($from, $to, $group));
    }
}
