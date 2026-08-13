<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\TransactionHistory;
use App\Models\TransactionRequirement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class TransactionRequirementController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Store an uploaded requirement file
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'transaction_id' => 'required|exists:transaction_history,id',
                'requirement_type' => 'required|in:valid_id,death_certificate,funeral_contract',
                'no_file' => 'sometimes|boolean',
                'file' => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png,gif',
            ]);

            $transaction = TransactionHistory::findOrFail($validated['transaction_id']);
            $noFile = $request->boolean('no_file');

            if (!$noFile && !$request->hasFile('file')) {
                throw ValidationException::withMessages([
                    'file' => ['Please upload a file or check the no-file option.'],
                ]);
            }

            // Delete existing file if present
            $existing = TransactionRequirement::where('transaction_id', $validated['transaction_id'])
                ->where('requirement_type', $validated['requirement_type'])
                ->first();

            if ($existing && $existing->file_path) {
                Storage::disk('public')->delete($existing->file_path);
            }

            if ($noFile) {
                $requirement = TransactionRequirement::updateOrCreate(
                    [
                        'transaction_id' => $validated['transaction_id'],
                        'requirement_type' => $validated['requirement_type'],
                    ],
                    [
                        'file_path' => null,
                        'file_name' => null,
                        'mime_type' => null,
                        'file_size' => 0,
                    ]
                );

                ActivityLog::create([
                    'user_id' => auth()->id(),
                    'action' => 'requirement_updated',
                    'description' => "Saved requirement '{$validated['requirement_type']}' without a file for transaction {$transaction->transaction_id}.",
                    'subject_type' => 'TransactionRequirement',
                    'subject_id' => $requirement->id,
                    'properties' => json_encode(['transaction_id' => $transaction->id, 'requirement_type' => $validated['requirement_type'], 'has_file' => false]),
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Requirement saved without a file.',
                    'data' => [
                        'id' => $requirement->id,
                        'file_path' => null,
                        'file_name' => null,
                    ],
                ]);
            }

            // Store the new file
            $file = $request->file('file');
            $fileName = "{$transaction->transaction_id}_{$validated['requirement_type']}_" . time() . '.' . $file->getClientOriginalExtension();
            $filePath = $file->storeAs('transaction_requirements', $fileName, 'public');

            // Create or update the requirement record
            $requirement = TransactionRequirement::updateOrCreate(
                [
                    'transaction_id' => $validated['transaction_id'],
                    'requirement_type' => $validated['requirement_type'],
                ],
                [
                    'file_path' => $filePath,
                    'file_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getClientMimeType(),
                    'file_size' => $file->getSize(),
                ]
            );

            ActivityLog::create([
                'user_id' => auth()->id(),
                'action' => 'requirement_uploaded',
                'description' => "Uploaded requirement '{$validated['requirement_type']}' ({$file->getClientOriginalName()}) for transaction {$transaction->transaction_id}.",
                'subject_type' => 'TransactionRequirement',
                'subject_id' => $requirement->id,
                'properties' => json_encode(['transaction_id' => $transaction->id, 'requirement_type' => $validated['requirement_type'], 'file_name' => $file->getClientOriginalName(), 'has_file' => true]),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'File uploaded successfully.',
                'data' => [
                    'id' => $requirement->id,
                    'file_path' => asset('storage/' . $filePath),
                    'file_name' => $file->getClientOriginalName(),
                ],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get requirement files for a transaction
     */
    public function show($transactionId)
    {
        try {
            $transaction = TransactionHistory::findOrFail($transactionId);
            $requirements = TransactionRequirement::where('transaction_id', $transactionId)->get();

            return response()->json([
                'success' => true,
                'data' => $requirements,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a requirement file
     */
    public function destroy($requirementId)
    {
        try {
            $requirement = TransactionRequirement::findOrFail($requirementId);
            $transaction = TransactionHistory::find($requirement->transaction_id);

            if ($requirement->file_path) {
                Storage::disk('public')->delete($requirement->file_path);
            }

            $requirement->delete();

            ActivityLog::create([
                'user_id' => auth()->id(),
                'action' => 'requirement_deleted',
                'description' => "Deleted requirement '{$requirement->requirement_type}'" . ($transaction ? " for transaction {$transaction->transaction_id}" : '') . '.',
                'subject_type' => 'TransactionRequirement',
                'subject_id' => $requirementId,
                'properties' => json_encode(['requirement_type' => $requirement->requirement_type, 'transaction_id' => $requirement->transaction_id]),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'File deleted successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Download a requirement file
     */
    public function download($requirementId)
    {
        try {
            $requirement = TransactionRequirement::findOrFail($requirementId);

            if (!$requirement->file_path || !Storage::disk('public')->exists($requirement->file_path)) {
                return response()->json([
                    'success' => false,
                    'message' => 'File not found.',
                ], 404);
            }

            return Storage::disk('public')->download($requirement->file_path, $requirement->file_name);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
