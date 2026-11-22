<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_dashboard_redirects_to_the_start_page_of_the_role()
    {
        $this->signIn(User::factory()->student()->create());
        $this->get(route('dashboard'))->assertRedirect(route('student.dashboard'));
        // P10: trang bắt đầu Học viên có mục gợi ý khóa (rỗng khi chưa có khóa đang bán).
        $this->get(route('student.dashboard'))->assertOk()
            ->assertInertia(fn ($page) => $page->component('student/dashboard')->has('suggestedCourses', 0));
    }
}
