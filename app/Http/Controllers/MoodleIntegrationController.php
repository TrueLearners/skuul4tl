<?php

namespace App\Http\Controllers;

use App\Models\SchoolIntegration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MoodleIntegrationController extends Controller
{
    private function integration(): SchoolIntegration
    {
        $school = current_school();
        abort_unless($school, 404);
        $this->authorize('update', $school);

        return SchoolIntegration::firstOrNew(['school_id' => $school->id]);
    }

    public function edit(): View
    {
        $integration = $this->integration();

        return view('pages.integrations.moodle', compact('integration'));
    }

    public function update(Request $request): RedirectResponse
    {
        $integration = $this->integration();
        $data = $request->validate([
            'moodle_base_url' => ['required', 'url', 'max:255'],
            'moodle_token' => ['nullable', 'string', 'max:2048'],
            'moodle_enabled' => ['nullable', 'boolean'],
        ]);

        if (parse_url($data['moodle_base_url'], PHP_URL_SCHEME) !== 'https') {
            throw ValidationException::withMessages([
                'moodle_base_url' => 'Use your Moodle site HTTPS address.',
            ]);
        }

        $data['moodle_base_url'] = rtrim($data['moodle_base_url'], '/');
        if (!filled($data['moodle_token'])) {
            unset($data['moodle_token']);
        }
        $data['moodle_enabled'] = $request->boolean('moodle_enabled');
        $integration->fill($data);
        $integration->school_id = current_school()->id;
        $integration->save();

        return back()->with('success', 'Moodle settings saved.');
    }

    public function test(): RedirectResponse
    {
        $integration = $this->integration();
        abort_unless($integration->exists && filled($integration->moodle_base_url) && filled($integration->moodle_token), 422, 'Save the Moodle URL and token first.');

        try {
            $response = Http::asForm()
                ->acceptJson()
                ->timeout(20)
                ->post($integration->moodle_base_url.'/webservice/rest/server.php', [
                    'wstoken' => $integration->moodle_token,
                    'wsfunction' => 'core_webservice_get_site_info',
                    'moodlewsrestformat' => 'json',
                ]);
        } catch (\Throwable $exception) {
            report($exception);

            return back()->withErrors(['moodle' => 'Could not reach Moodle. Check the URL and try again.']);
        }

        $body = $response->json();
        if (!$response->successful() || !is_array($body) || isset($body['exception']) || !isset($body['sitename'])) {
            return back()->withErrors(['moodle' => 'Moodle rejected the connection. Check the token and confirm the web service is enabled.']);
        }

        $integration->forceFill(['moodle_last_tested_at' => now()])->save();

        return back()->with('success', 'Connected to Moodle: '.$body['sitename']);
    }
}
