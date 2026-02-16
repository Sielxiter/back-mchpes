<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SettingsAdminController extends Controller
{
    private const KEY_APP_NAME = 'app_name';
    private const KEY_CONTACT_EMAIL = 'contact_email';
    private const KEY_CANDIDATURE_OPEN = 'candidature_open';

    public function index(): JsonResponse
    {
        $keys = [
            self::KEY_APP_NAME,
            self::KEY_CONTACT_EMAIL,
            self::KEY_CANDIDATURE_OPEN,
        ];

        $rows = Setting::query()->whereIn('key', $keys)->get(['key', 'value']);
        $map = $rows->pluck('value', 'key');

        return response()->json([
            'data' => [
                self::KEY_APP_NAME => (string) ($map[self::KEY_APP_NAME] ?? config('app.name', 'Application')),
                self::KEY_CONTACT_EMAIL => (string) ($map[self::KEY_CONTACT_EMAIL] ?? ''),
                self::KEY_CANDIDATURE_OPEN => filter_var(($map[self::KEY_CANDIDATURE_OPEN] ?? '1'), FILTER_VALIDATE_BOOL),
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            self::KEY_APP_NAME => 'required|string|max:255',
            self::KEY_CONTACT_EMAIL => 'nullable|email|max:255',
            self::KEY_CANDIDATURE_OPEN => 'required|boolean',
        ], [
            self::KEY_APP_NAME . '.required' => 'Le nom de l\'application est requis',
            self::KEY_CONTACT_EMAIL . '.email' => 'Email de contact invalide',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        $this->upsertSetting(self::KEY_APP_NAME, (string) $data[self::KEY_APP_NAME]);
        $this->upsertSetting(self::KEY_CONTACT_EMAIL, (string) ($data[self::KEY_CONTACT_EMAIL] ?? ''));
        $this->upsertSetting(self::KEY_CANDIDATURE_OPEN, ((bool) $data[self::KEY_CANDIDATURE_OPEN]) ? '1' : '0');

        return response()->json([
            'message' => 'Paramètres enregistrés',
            'data' => [
                self::KEY_APP_NAME => (string) $data[self::KEY_APP_NAME],
                self::KEY_CONTACT_EMAIL => (string) ($data[self::KEY_CONTACT_EMAIL] ?? ''),
                self::KEY_CANDIDATURE_OPEN => (bool) $data[self::KEY_CANDIDATURE_OPEN],
            ],
        ]);
    }

    private function upsertSetting(string $key, string $value): void
    {
        Setting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
    }
}
