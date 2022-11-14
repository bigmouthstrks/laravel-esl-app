<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Tracking;
use App\Models\UserResponse;
use App\Models\Exercise;
use App\Http\Requests\StoreMultipleChoiceRequest;
use App\Http\Requests\StoreDragAndDropRequest;
use App\Http\Requests\StoreOpenEndedRequest;
use App\Http\Requests\StoreFillInTheGapsRequest;
use App\Http\Requests\StoreVoiceRecognitionRequest;

class TrackingController extends Controller
{
    public function index()
    {
        $tracking = Tracking::all();
        if ($tracking != null)  {
            return view('tracking.index', compact('tracking'));
        } else if($tracking == null) {
            $tracking = array();
            return view('tracking.index', compact('tracking'));
        }
    }

    public function store(Request $request, $exercise_id, $user_id)
    {
        $exercise = Exercise::find($exercise_id);
        $tracking = new Tracking;

        $intent_number = Tracking::where('exercise_id', $exercise->id)->count();
        $intent_number += 1;

        $tracking->intent_number = "$intent_number";

        if($request->time == null) { 
            $tracking->time_spent_in_minutes = "00:00";
        } else {
            $tracking->time_spent_in_minutes = $request->time;
        }

        if($request->correct == null) {
            $tracking->correct_answers = "0";
        } else {
            $tracking->correct_answers = $request->correct;
        }

        if($request->wrong == null) {
            $tracking->wrong_answers = "0";
        } else {
            $tracking->wrong_answers = $request->wrong;
        }

        $tracking->exercise_id = $exercise_id;
        $tracking->user_id = $user_id;
        $tracking->save();

        if($exercise != null and $exercise->exerciseType->underscore_name != 'form')
        {
            if ($request->responses != null) 
            {
                foreach($request->responses as $id=>$response)
                {
                    $user_response = new UserResponse;
                    if($response == null) $response = "";
                    $user_response->response = $response;
                    $user_response->question_id = $id;
                    $user_response->tracking_id = $tracking->id;
                    $user_response->save();
                }        
            }
        } else {
            foreach($exercise->questions as $question)
            {
                // EXCLUSIVE RESPONSES IS FALSE
                if (!$question->exclusive_responses)
                {
                    if ($request->answers != null) 
                    {
                        foreach($request->answers as $id=>$answer)
                        {
                            $question_id = $id;
                            foreach($answer as $a) 
                            {
                                foreach($a as $response)
                                {
                                    $user_response = new UserResponse;
                                    $user_response->response = $response;
                                    $user_response->question_id = $question_id;
                                    $user_response->tracking_id = $tracking->id;
                                    $user_response->save();
                                }
                            }
                        }
                    }
                // FIXME: EXCLUSIVE RESPONSES IS TRUE
                } else {
                    if ($request->answers != null)
                    {
                        foreach($request->answers as $id=>$answer) 
                        {
                            $question_id = $id;
                            foreach($answer as $response) 
                            {
                                $user_response = new UserResponse;
                                $user_response->response = $response;
                                $user_response->question_id = $question_id;
                                $user_response->tracking_id = $tracking->id;
                                $user_response->save();
                            }
                        }
                    }
                }
            }
        }

        $message = '🎉 Submission completed successfully! 🎉';
        if(count($exercise->feedbacks) > 0) {
            $message = $exercise->feedbacks->where('feedback_type_id', 1)->first()->message;
        }

        $status = $this->exerciseStatus($exercise, $user_id);

        return response()->json([
            'result' => 'success',
            'feedback_message' => "$message",
            'status_message' => $status['message'],
            'navigation_url' => $status['url'],
            'navigation_type' => $status['type']
        ]);
    }

    public function show($id) 
    {
        $tracking = Tracking::find($id);
        return view('tracking.show', compact('tracking'));
    }

    private function sectionStatus($exercise, $user_id) {
        $section = $exercise->section;
        $exercises = $section->exercises->where('exercise_type_id', '!=', 5);
        $section_completed = false;
        $completed_exercises = [];

        foreach($exercises as $exercise) {
            $tracking = Tracking::where('user_id', $user_id)->where('exercise_id', $exercise->id)->get();
            $tracking_count = count($tracking);
            if($tracking_count >= 1) array_push($completed_exercises, $exercise->id);
        }
        if(count($exercises) == count($completed_exercises)) $section_completed = true;

        return $section_completed;
    }

    private function unitStatus($exercise, $user_id) {
        $unit = $exercise->section->unit;
        $unit_completed = false;
        $sections_completed = [];
        $completed_exercises = [];

        foreach($unit->sections as $section) {
            $exercises = $section->exercises->where('exercise_type_id', '!=', 5);

            foreach($exercises as $exercise) {
                $tracking = Tracking::where('exercise_id', $exercise->id)->where('user_id', $user_id)->get();
                $tracking_count = count($tracking);
                array_push($completed_exercises, $exercise->id);
            }

            if (count($completed_exercises) == count($exercises)) array_push($sections_completed, $section->id);
        }
        if(count($unit->sections) == count($sections_completed)) $unit_completed = true;

        return $unit_completed;
    }

    private function getNextExercise($exercise, $user_id) {
        // Buscar primer ejercicio sin respuesta registrado que no sea de tipo Voice Recognition

        $section = $exercise->section;
        $exercises = $section->exercises->where('exercise_type_id', '!=', 5);
        $next_exercise;

        foreach($exercises as $exercise)
        {
            $exercise_tracking_count = Tracking::where('exercise_id', $exercise->id)->where('user_id', $user_id)->count();
            if($exercise_tracking_count == 0) {
                $next_exercise = $exercise;
                break;
            }
        }

        $url = $next_exercise->exerciseType->underscore_name . $next_exercise->id;

        return $url;
    }

    private function getNextSection($exercise, $user_id) {
        // Buscar primer ejercicio sin respuesta registrado que no sea de tipo Voice Recognition

        $section = $exercise->section;
        $unit = $section->unit;
        $next_section;

        foreach($unit->sections as $section) {
            $exercises = $section->exercises->where('exercise_type_id', '!=', 5);
            foreach($exercises as $exercise)
            {
                $exercise_tracking_count = Tracking::where('exercise_id', $exercise->id)->where('user_id', $user_id)->count();
                if($exercise_tracking_count == 0) {
                    $next_section = $exercise->section;
                    break;
                }
            }
        }

        $url = $next_section->underscore_name;

        return $url;
    }

    private function getNextUnit($exercise, $user_id) {
        $section = $exercise->section;
        $unit = $section->unit;
        $user = User::find($user_id);
        $group = Group::find($user->group_id);
        $units = $group->units;

        $next_unit;

        foreach($units as $unit) {
            foreach($unit->sections as $section) {
                $exercises = $section->exercises->where('exercise_type_id', '!=', 5);
                foreach($exercises as $exercise)
                {
                    $exercise_tracking_count = Tracking::where('exercise_id', $exercise->id)->where('user_id', $user_id)->count();
                    if($exercise_tracking_count == 0) {
                        $next_unit = $exercise->unit;
                        break;
                    }
                }
            }
        }

        $url = route('student.show', "$next_unit->id");

        return $url;
    }

    private function exerciseStatus($exercise, $user_id) {
        $section_completed = $this->sectionStatus($exercise, $user_id);
        $unit_completed = $this->unitStatus($exercise, $user_id);

        if ($unit_completed) {
            $next_unit_url = $this->getNextUnit($exercise, $user_id);
            return ["message" => "You completed this unit, now move to the next unit", "url" => "$next_unit_url", 'type' => 'unit'];
        } else if ($section_completed) {
            $next_section_url = $this->getNextSection($exercise, $user_id);
            return ["message" => "You completed this stage now continue with the next one", "url" => "$next_section_url", 'type' => 'section'];
        } else {
            $next_exercise_url = $this->getNextExercise($exercise, $user_id);
            return ["message" => "You completed this exercise now continue with the next one", "url" => "$next_exercise_url", 'type' => 'exercise'];
        }
    }
}