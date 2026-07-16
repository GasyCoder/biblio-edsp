<?php

use App\Models\Student;
use App\Models\User;
use App\Services\LoanService;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn()=> $this->seed(RolePermissionSeeder::class));

it('lists loans and overdue statistics for staff',function(){
    $staff=User::factory()->create()->assignRole('secretaire');
    $student=Student::factory()->create();
    $loan=app(LoanService::class)->open($student,now()->addWeek()->format('Y-m-d'),$staff);
    $loan->update(['due_at'=>now()->subDay()]);
    $this->actingAs($staff)->get(route('loans.index',['status'=>'overdue']))->assertInertia(fn(Assert $page)=>$page->component('Loans/Index')->has('loans.data',1)->where('stats.overdue',1)->where('ownOnly',false));
});

it('restricts students to their own loans',function(){
    $user=User::factory()->create()->assignRole('etudiant');$staff=User::factory()->create()->assignRole('secretaire');
    $student=Student::factory()->create(['user_id'=>$user->id]);$other=Student::factory()->create();
    app(LoanService::class)->open($student,now()->addWeek()->format('Y-m-d'),$staff);app(LoanService::class)->open($other,now()->addWeek()->format('Y-m-d'),$staff);
    $this->actingAs($user)->get(route('loans.index'))->assertInertia(fn(Assert $page)=>$page->has('loans.data',1)->where('loans.data.0.student_id',$student->id)->where('ownOnly',true));
});
