<?php

namespace Tests\Feature\Activities;

use Tests\TestCase;
use App\Models\User;
use App\Models\Timetable;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CreateActivityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function user_can_create_an_activity(): void
    {
        $user = User::factory()->create();
        $timetable = Timetable::factory()->for($user)->create();

        $payload = [
            'timetable_id' => $timetable->id,
            'day' => 2,
            'start_time' => '10:30',
            'duration' => 90,
            'info' => 'Clase de matemáticas',
            'is_available' => true,
        ];

        $response = $this
            ->actingAs($user)
            ->postJson('/api/activities', $payload);

        $response->assertCreated();
        $response->assertJsonFragment([
            'message' => 'Actividad creada correctamente',
        ]);

        $this->assertDatabaseHas('activities', $payload);
    }

    #[Test]
    public function guest_cannot_create_an_activity(): void
    {
        $response = $this->postJson('/api/activities', []);

        $response->assertUnauthorized();
    }

    #[Test]
    public function user_cannot_create_activity_in_timetable_they_do_not_own(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();

        $timetable = Timetable::factory()->for($owner)->create();

        $payload = [
            'timetable_id' => $timetable->id,
            'day' => 3,
            'start_time' => '14:00',
            'duration' => 60,
            'info' => 'Intento no autorizado',
            'is_available' => true,
        ];

        $response = $this
            ->actingAs($intruder)
            ->postJson('/api/activities', $payload);

        $response->assertForbidden();
        $response->assertJsonFragment([
            'message' => 'No tienes permiso para modificar este horario',
        ]);
    }

    #[Test]
    public function timetable_id_is_required_and_must_exist(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->postJson('/api/activities', [
                // falta 'timetable_id'
                'day' => 8,
                'start_time' => '09:00',
                'duration' => 0,
                'info' => 'Clase',
                'is_available' => true,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['timetable_id', 'day', 'duration']);

        // ahora con un ID inexistente
        $response = $this
            ->actingAs($user)
            ->postJson('/api/activities', [
                'timetable_id' => 9999, // no existe
                'day' => 1,
                'start_time' => '09:00',
                'duration' => 60,
                'info' => 'Clase'
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['timetable_id']);
    }
}
