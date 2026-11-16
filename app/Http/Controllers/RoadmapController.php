<?php

namespace App\Http\Controllers;

class RoadmapController extends Controller
{
    public function page(OperationsController $operations)
    {
        abort_unless(in_array('roadmap', $operations->sections(), true), 403);

        return view('app', ['section' => 'roadmap', 'sections' => $operations->sections(), 'roadmapWorkspace' => true, 'phases' => config('roadmap.phases'), 'estimateNote' => config('roadmap.estimate_note')]);
    }
}
