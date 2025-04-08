<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Http\Requests\Candidate\CredentialRequest;
use App\Models\CandidateCredential;
use App\Services\CandidateService;
use Illuminate\Http\JsonResponse;

/**
 * Controller for managing candidate credentials
 */
class CredentialsController extends Controller
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
        $this->middleware('auth:api');
        $this->middleware('role:candidate');
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

        return response()->json([
            'success' => true,
            'message' => 'Credential added successfully',
            'data' => $credential,
        ], 201);
    }

    /**
     * Update credential
     *
     * @param CredentialRequest $request
     * @return JsonResponse
     */
    public function update(CredentialRequest $request): JsonResponse
    {
        $user = auth()->user();
        $data = $request->validated();

        $credential = CandidateCredential::findOrFail($data['id']);

        // Check if the credential belongs to the authenticated user
        if ($credential->candidate_id !== $user->candidate->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $credential = $this->candidateService->updateCredential($credential, $data);

        return response()->json([
            'success' => true,
            'message' => 'Credential updated successfully',
            'data' => $credential,
        ]);
    }

    /**
     * Delete credential
     *
     * @param int $id
     * @return JsonResponse
     */
    public function delete($id): JsonResponse
    {
        $user = auth()->user();
        $credential = CandidateCredential::findOrFail($id);

        // Check if the credential belongs to the authenticated user
        if ($credential->candidate_id !== $user->candidate->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $this->candidateService->deleteCredential($credential);

        return response()->json([
            'success' => true,
            'message' => 'Credential deleted successfully',
        ]);
    }
}
