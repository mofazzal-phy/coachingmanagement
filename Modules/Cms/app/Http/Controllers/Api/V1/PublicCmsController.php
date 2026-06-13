<?php

namespace Modules\Cms\app\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Modules\Cms\app\Models\ContactMessage;
use Modules\Cms\app\Services\PublicCmsService;
use Modules\Core\app\Http\Controllers\BaseApiController;
use Modules\Teacher\app\Models\Teacher;

class PublicCmsController extends BaseApiController
{
    public function __construct(
        protected PublicCmsService $publicCmsService
    ) {}

    public function home(): JsonResponse
    {
        return $this->success($this->publicCmsService->home());
    }

    public function sliders(): JsonResponse
    {
        return $this->success($this->publicCmsService->listSliders());
    }

    public function blog(Request $request): JsonResponse
    {
        $perPage = min(
            (int) $request->input('per_page', config('cms.public.default_per_page', 12)),
            (int) config('cms.public.max_per_page', 50)
        );

        return $this->paginatedResponse($this->publicCmsService->paginateBlog($perPage));
    }

    public function blogBySlug(string $slug): JsonResponse
    {
        $post = $this->publicCmsService->getBlogBySlug($slug);

        if (!$post) {
            return $this->notFound('Blog post not found');
        }

        return $this->success($post);
    }

    public function pageBySlug(string $slug): JsonResponse
    {
        $page = $this->publicCmsService->getPageBySlug($slug);

        if (!$page) {
            return $this->notFound('Page not found');
        }

        return $this->success($page);
    }

    public function testimonials(): JsonResponse
    {
        return $this->success($this->publicCmsService->listTestimonials());
    }

    public function galleries(Request $request): JsonResponse
    {
        return $this->success(
            $this->publicCmsService->listGalleries(null, $request->input('category'))
        );
    }

    public function successStories(): JsonResponse
    {
        return $this->success($this->publicCmsService->listSuccessStories());
    }

    public function successStoryBySlug(string $slug): JsonResponse
    {
        $story = $this->publicCmsService->getSuccessStoryBySlug($slug);

        if (!$story) {
            return $this->notFound('Success story not found');
        }

        return $this->success($story);
    }

    public function downloads(): JsonResponse
    {
        return $this->success($this->publicCmsService->listDownloads());
    }

    public function downloadFile(string $id): JsonResponse
    {
        $resource = $this->publicCmsService->downloadResource($id);

        if (!$resource) {
            return $this->notFound('Download not available');
        }

        return $this->success($resource);
    }

    public function events(): JsonResponse
    {
        return $this->success($this->publicCmsService->listEvents());
    }

    public function notices(): JsonResponse
    {
        return $this->success($this->publicCmsService->listNotices());
    }

    /**
     * Public list of teachers (safe fields only).
     */
    public function teachers(Request $request): JsonResponse
    {
        $limit = min((int) $request->input('limit', 24), 60);

        $teachers = Teacher::with('subjects:id,name')
            ->where('status', 'active')
            ->orderBy('experience_years', 'desc')
            ->limit($limit)
            ->get();

        $data = $teachers->map(fn ($t) => $this->transformTeacher($t));

        return $this->success($data);
    }

    /**
     * Public teacher profile (safe fields only).
     */
    public function teacher(string $id): JsonResponse
    {
        $teacher = Teacher::with('subjects:id,name')
            ->where('status', 'active')
            ->find($id);

        if (!$teacher) {
            return $this->notFound('Teacher not found');
        }

        return $this->success($this->transformTeacher($teacher, true));
    }

    /**
     * Public branding / contact settings for the marketing site.
     */
    public function siteSettings(): JsonResponse
    {
        return $this->success([
            'brand_name' => config('app.name', 'Poralekha'),
            'phone' => config('cms.public.contact_phone'),
            'whatsapp' => config('cms.public.contact_whatsapp'),
            'email' => config('cms.public.contact_email', config('mail.from.address')),
            'address' => config('cms.public.contact_address'),
        ]);
    }

    /**
     * Public contact form submission: persists the lead and emails the team.
     */
    public function contact(Request $request): JsonResponse
    {
        // Honeypot — bots fill hidden fields a human never sees.
        if (filled($request->input('website')) || filled($request->input('company'))) {
            return response()->json(['success' => true, 'message' => 'Thank you for contacting us.'], 200);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120', 'regex:/^[\pL\pM .\'-]+$/u'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^[0-9+\-\s]{6,30}$/'],
            'email' => 'nullable|email:rfc|max:150',
            'subject' => 'nullable|string|max:160',
            'message' => 'required|string|max:3000',
        ]);

        $record = ContactMessage::create([
            ...$validated,
            'source' => 'public_site',
            'status' => 'new',
            'ip_address' => $request->ip(),
        ]);

        // Notify the team by email; never let mail failures break the response.
        try {
            $to = config('cms.public.contact_email', config('mail.from.address'));
            if ($to) {
                $body = "New contact message from the website\n\n"
                    . "Name: {$validated['name']}\n"
                    . "Phone: {$validated['phone']}\n"
                    . 'Email: ' . ($validated['email'] ?? 'N/A') . "\n"
                    . 'Subject: ' . ($validated['subject'] ?? 'N/A') . "\n\n"
                    . "Message:\n{$validated['message']}\n";

                Mail::raw($body, function ($m) use ($to, $validated) {
                    $m->to($to)->subject('Website Contact: ' . ($validated['subject'] ?? $validated['name']));
                    if (!empty($validated['email'])) {
                        $m->replyTo($validated['email'], $validated['name']);
                    }
                });
            }
        } catch (\Throwable $e) {
            Log::warning('Contact email failed: ' . $e->getMessage());
        }

        return $this->created($record, 'Your message has been sent successfully.');
    }

    private function transformTeacher(Teacher $t, bool $detailed = false): array
    {
        $data = [
            'id' => $t->id,
            'name' => trim($t->first_name . ' ' . $t->last_name),
            'designation' => $t->specialization ?: $t->teacher_type,
            'subject' => $t->subjects->pluck('name')->implode(', ') ?: $t->specialization,
            'qualification' => $t->qualification,
            'experience' => $t->experience_years ? $t->experience_years . '+ yrs' : null,
            'photo_url' => $t->photo_url,
        ];

        if ($detailed) {
            $data['previous_institution'] = $t->previous_institution;
            $data['subjects'] = $t->subjects->pluck('name');
        }

        return $data;
    }

    public function track(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'content_type' => 'required|string|max:80',
            'content_id' => 'required|uuid',
            'event_type' => 'required|in:view,download',
        ]);

        return $this->success(
            $this->publicCmsService->trackEvent(
                $validated['content_type'],
                $validated['content_id'],
                $validated['event_type']
            )
        );
    }
}
