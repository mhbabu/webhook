<?php

namespace App\Http\Controllers\Api\SystemSetting;

use App\Http\Controllers\Controller;
use App\Http\Requests\SystemSetting\StoreSystemSettingRequest;
use App\Http\Requests\SystemSetting\UpdateSystemSettingRequest;
use App\Http\Resources\SystemSetting\SystemSettingResource;
use Illuminate\Http\Request;
use App\Models\SystemSetting;

class SystemSettingController extends Controller
{
    public function index(Request $request)
    {
        $settings = SystemSetting::all();
        return jsonResponse('System settings retrieved successfully', true, SystemSettingResource::collection($settings));
    }

    public function store(StoreSystemSettingRequest $request)
    {
        $setting = SystemSetting::create([
            'setting_key'   => $request->setting_key,
            'setting_value' => $request->setting_value,
        ]);

        return jsonResponse('System setting created successfully', true, new SystemSettingResource($setting));
    }

    public function show($id)
    {
        $setting = SystemSetting::find($id);
        if (!$setting) {
            return jsonResponse('Setting not found', false, null, 404);
        }

        return jsonResponse('Setting retrieved successfully', true, new SystemSettingResource($setting));
    }

    public function update(UpdateSystemSettingRequest $request, $id)
    {
        $setting = SystemSetting::find($id);
        if (!$setting) {
            return jsonResponse('Setting not found', false, null, 404);
        }

        $setting->update([
            'setting_value' => $request->setting_value,
        ]);

        return jsonResponse('Setting updated successfully', true, new SystemSettingResource($setting));
    }

    public function destroy($id)
    {
        $setting = SystemSetting::find($id);
        if (!$setting) {
            return jsonResponse('Setting not found', false, null, 404);
        }

        $setting->delete();
        return jsonResponse('Setting deleted successfully', true);
    }
}

