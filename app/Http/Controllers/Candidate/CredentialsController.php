<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Http\Requests\Candidate\CredentialRequest;
use App\Http\Resources\CredentialResource;
use App\Models\CandidateCredential;
use App\Services\CandidateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Controller for managing candidate credentials
 */
class CredentialsController extends Controller implements HasMiddleware
{
    /**
     * Candidate service instance
     *
     * @var CandidateService
     */
    protected CandidateService $candidateService;

    /**
     * Create a new controller instance.
     *
     * @param CandidateService $candidateService
     * @return void
     */
    public function __construct(CandidateService $candidateService)
    {
        $this->candidateService = $candidateService;
    }

    /**
     * Get the middleware that should be assigned to the controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware(['auth:api','role:candidate','role:admin']),
        ];
    }

    /**
     * Get all credentials for the authenticated candidate
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $user = auth()->user();
        $candidate = $user->candidate;

        $credentials = $this->candidateService->getCredentials($candidate);

        return response()->success(
            CredentialResource::collection($credentials),
            'Credentials retrieved successfully'
        );
    }

    /**
     * Store a new credential
     *
     * @param CredentialRequest $request
     * @return JsonResponse
     */
    public function store(CredentialRequest $request): JsonResponse
    {
        $user = auth()->user();
        $candidate = $user->candidate;
        $data = $request->validated();

        $credential = $this->candidateService->addCredential($candidate, $data);

        return response()->created(new CredentialResource($credential), 'Credential added successfully');
    }

    /**
     * Update credential
     *
     * @param CredentialRequest $request
     * @return JsonResponse
     */
    public function update(int $id, CredentialRequest $request): JsonResponse
    {
        $user = auth()->user();
        $data = $request->validated();

        $credential = CandidateCredential::query()->findOrFail($id);

        // Check if the credential belongs to the authenticated user
        if ($credential->candidate_id !== $user->candidate->id) {
            return response()->forbidden('Unauthorized');
        }

        $credential = $this->candidateService->updateCredential($credential, $data);

        return response()->success(new CredentialResource($credential), 'Credential updated successfully');
    }

    /**
     * Delete credential
     *
     * @param int $id
     * @return JsonResponse
     */
    public function delete(int $id): JsonResponse
    {
        $user = auth()->user();
        $credential = CandidateCredential::query()->findOrFail($id);

        // Check if the credential belongs to the authenticated user
        if ($credential->candidate_id !== $user->candidate->id) {
            return response()->forbidden('Unauthorized');
        }

        $this->candidateService->deleteCredential($credential);

        return response()->success(null, 'Credential deleted successfully');
    }
}
