<?php
use App\Models\Student;use App\Models\User;use App\Services\VisitService;use Database\Seeders\RolePermissionSeeder;use Inertia\Testing\AssertableInertia as Assert;
beforeEach(fn()=> $this->seed(RolePermissionSeeder::class));
it('shows operational reports for authorized staff',function(){ $staff=User::factory()->create()->assignRole('secretaire');$student=Student::factory()->create();app(VisitService::class)->checkIn($student,$staff);$this->actingAs($staff)->get(route('reports.index'))->assertInertia(fn(Assert $page)=>$page->component('Reports/Index')->where('metrics.visits',1)->where('metrics.uniqueStudents',1)->has('inventory',6));});
it('prevents students from opening operational reports',function(){ $user=User::factory()->create()->assignRole('etudiant');$this->actingAs($user)->get(route('reports.index'))->assertForbidden();});
