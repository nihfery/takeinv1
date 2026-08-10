<?php

namespace App\Modules\Provider\Presentation\Web;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Application\Actions\RecordAuditEvent;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Provider\Application\Services\ProviderDocumentStorage;
use App\Modules\Provider\Application\Support\ProviderMenuAccess;
use App\Modules\Provider\Infrastructure\Persistence\Models\ProviderProfile;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class ProviderDocumentController extends Controller
{
    public function __construct(
        private readonly ProviderDocumentStorage $documents,
        private readonly RecordAuditEvent $audit,
    ) {
    }

    public function provider(string $document): Response
    {
        $user = Auth::guard('provider')->user();

        abort_unless($user instanceof User && ProviderMenuAccess::isProviderOwner($user), 403);

        $profile = ProviderProfile::query()
            ->where('user_id', $user->id)
            ->firstOrFail();

        $response = $this->documents->response($profile, $document);

        $this->audit->execute(
            'provider.document.accessed',
            ProviderProfile::class,
            $profile->id,
            after: ['document' => $document, 'channel' => 'provider-web'],
            actor: $user,
            providerId: (int) $user->id,
        );

        return $response;
    }

    public function admin(User $user, string $document): Response
    {
        abort_unless(
            $user->role === 'provider'
                && $user->provider_id === null
                && $user->provider_role_id === null,
            404,
        );

        $profile = ProviderProfile::query()
            ->where('user_id', $user->id)
            ->firstOrFail();

        $response = $this->documents->response($profile, $document);

        $this->audit->execute(
            'provider.document.accessed',
            ProviderProfile::class,
            $profile->id,
            after: ['document' => $document, 'channel' => 'admin-web'],
            actor: Auth::guard('admin')->user(),
            providerId: (int) $user->id,
        );

        return $response;
    }
}
