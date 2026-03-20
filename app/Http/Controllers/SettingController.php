<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SettingController extends Controller
{
    public function show()
    {
        return Setting::first();
    }


    public function update(Request $request)
    {
        $data = $request->validate([
            'site_name' => 'required|string|max:255',
            'logo' => 'nullable|image',
            'favicon' => 'nullable|image',
            'colors' => 'nullable|array',
            'socials' => 'nullable|array',
            'socials.*.name' => 'required|string',
            'socials.*.link' => 'required|url',
            'socials.*.logo' => 'nullable|image',
        ]);

        // ✅ Get or create settings row
        $setting = Setting::firstOrCreate([]);

        /* ======================
           LOGO
        ====================== */
        if ($request->hasFile('logo')) {
            // delete old logo
            if ($setting->logo && Storage::disk('public')->exists($setting->logo)) {
                Storage::disk('public')->delete($setting->logo);
            }

            $data['logo'] = $request->file('logo')->store('uploads', 'public');
        }

        /* ======================
           FAVICON
        ====================== */
        if ($request->hasFile('favicon')) {
            if ($setting->favicon && Storage::disk('public')->exists($setting->favicon)) {
                Storage::disk('public')->delete($setting->favicon);
            }

            $data['favicon'] = $request->file('favicon')->store('uploads', 'public');
        }

        /* ======================
           SOCIALS
        ====================== */
        if ($request->has('socials')) {
            $socials = [];
            $oldSocials = $setting->socials ?? [];

            foreach ($request->socials as $index => $social) {

                // delete old social logo if replaced
                if ($request->hasFile("socials.$index.logo")) {

                    if (
                        isset($oldSocials[$index]['logo']) &&
                        Storage::disk('public')->exists($oldSocials[$index]['logo'])
                    ) {
                        Storage::disk('public')->delete($oldSocials[$index]['logo']);
                    }

                    $social['logo'] = $request
                        ->file("socials.$index.logo")
                        ->store('uploads', 'public');
                } else {
                    // keep old logo if not replaced
                    $social['logo'] = $oldSocials[$index]['logo'] ?? null;
                }

                $socials[] = $social;
            }

            $data['socials'] = $socials;
        }

        $setting->update($data);

        return response()->json($setting);
    }


}
