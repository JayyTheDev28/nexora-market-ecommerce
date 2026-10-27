<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\AuditLog;
use App\Models\PlatformSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function index(): View
    {
        $settings = PlatformSetting::all()->pluck('value', 'key')->all();
        $announcements = Announcement::with('postedBy')->latest()->paginate(10);

        return view('admin.settings.index', compact('settings', 'announcements'));
    }

    public function updatePlatformSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'platform_name' => ['required', 'string', 'max:100'],
            'commission_rate' => ['required', 'numeric', 'min:0', 'max:50'],
            'return_window_days' => ['required', 'integer', 'min:1', 'max:60'],
            'support_email' => ['required', 'email', 'max:150'],
            'support_phone' => ['required', 'string', 'max:30'],
            'maintenance_mode' => ['nullable', 'boolean'],
            'maintenance_message' => ['nullable', 'string', 'max:500'],
        ]);

        $settingsToUpdate = [
            'platform_name' => ['value' => $validated['platform_name'], 'group' => 'general'],
            'commission_rate' => ['value' => $validated['commission_rate'], 'group' => 'commission'],
            'return_window_days' => ['value' => $validated['return_window_days'], 'group' => 'policy'],
            'support_email' => ['value' => $validated['support_email'], 'group' => 'contact'],
            'support_phone' => ['value' => $validated['support_phone'], 'group' => 'contact'],
            'maintenance_mode' => ['value' => !empty($validated['maintenance_mode']) ? '1' : '0', 'group' => 'system'],
            'maintenance_message' => ['value' => $validated['maintenance_message'] ?? '', 'group' => 'system'],
        ];

        $oldValues = [];
        $newValues = [];

        foreach ($settingsToUpdate as $key => $data) {
            $existing = PlatformSetting::where('key', $key)->first();
            $oldValues[$key] = $existing?->value;
            $newValues[$key] = $data['value'];

            PlatformSetting::updateOrCreate(
                ['key' => $key],
                ['value' => $data['value'], 'group' => $data['group']]
            );
        }

        $dummySetting = PlatformSetting::first() ?? new PlatformSetting();
        AuditLog::log(
            action: 'platform_settings_updated',
            auditable: $dummySetting,
            oldValues: $oldValues,
            newValues: $newValues,
            reason: 'Platform settings updated by administrator'
        );

        return back()->with('status', 'Platform configuration successfully saved.');
    }

    public function storeAnnouncement(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:5000'],
            'target_roles' => ['nullable', 'array'],
            'target_roles.*' => ['string', 'in:buyer,seller,sorting_center,courier'],
            'published_at' => ['nullable', 'date'],
        ]);

        $announcement = Announcement::create([
            'posted_by' => auth()->id(),
            'title' => $validated['title'],
            'body' => $validated['body'],
            'target_roles' => ! empty($validated['target_roles']) ? $validated['target_roles'] : null,
            'published_at' => ! empty($validated['published_at']) ? $validated['published_at'] : now(),
        ]);

        AuditLog::log(
            action: 'announcement_created',
            auditable: $announcement,
            oldValues: null,
            newValues: $announcement->toArray(),
            reason: "Posted broadcast announcement: {$announcement->title}"
        );

        return back()->with('status', 'Announcement successfully published.');
    }

    public function updateAnnouncement(Request $request, Announcement $announcement): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:5000'],
            'target_roles' => ['nullable', 'array'],
            'target_roles.*' => ['string', 'in:buyer,seller,sorting_center,courier'],
            'published_at' => ['nullable', 'date'],
        ]);

        $oldValues = $announcement->toArray();

        $announcement->update([
            'title' => $validated['title'],
            'body' => $validated['body'],
            'target_roles' => ! empty($validated['target_roles']) ? $validated['target_roles'] : null,
            'published_at' => ! empty($validated['published_at']) ? $validated['published_at'] : $announcement->published_at,
        ]);

        AuditLog::log(
            action: 'announcement_updated',
            auditable: $announcement,
            oldValues: $oldValues,
            newValues: $announcement->fresh()->toArray(),
            reason: "Updated announcement: {$announcement->title}"
        );

        return back()->with('status', 'Announcement updated.');
    }

    public function destroyAnnouncement(Announcement $announcement): RedirectResponse
    {
        $title = $announcement->title;
        $id = $announcement->id;

        $announcement->delete();

        // Note: we don't call AuditLog with deleted model instance since it's deleted, but we log via operator
        $dummySetting = PlatformSetting::first() ?? new PlatformSetting();
        AuditLog::log(
            action: 'announcement_deleted',
            auditable: $dummySetting,
            oldValues: ['announcement_id' => $id, 'title' => $title],
            newValues: null,
            reason: "Deleted announcement: {$title}"
        );

        return back()->with('status', "Announcement '{$title}' removed.");
    }
}
