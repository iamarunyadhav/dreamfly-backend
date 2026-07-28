<?php

namespace App\Http\Controllers;

use App\Support\Http\ApiResponse;
use Modules\Clients\Services\DocumentationTaskDeadlineService;

class DashboardController extends Controller
{
    use ApiResponse;

    public function summary(DocumentationTaskDeadlineService $deadlines)
    {
        return $this->ok($deadlines->summary());
    }
}
