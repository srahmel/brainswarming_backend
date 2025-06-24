<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEntryRequest;
use App\Http\Requests\UpdateEntryRequest;
use App\Http\Resources\EntryResource;
use App\Models\Entry;
use App\Models\Team;
use App\Services\EntryService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * @OA\Tag(
 *     name="Entries",
 *     description="API Endpoints for managing team entries"
 * )
 */
class EntryController extends Controller
{
    /**
     * The entry service instance.
     *
     * @var \App\Services\EntryService
     */
    protected $entryService;

    /**
     * Create a new controller instance.
     *
     * @param  \App\Services\EntryService  $entryService
     * @return void
     */
    public function __construct(EntryService $entryService)
    {
        $this->entryService = $entryService;
    }
    /**
     * Display a listing of the entries for a specific team.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $teamId
     * @return \Illuminate\Http\JsonResponse
     *
     * @OA\Get(
     *     path="/teams/{teamId}/entries",
     *     summary="Get all entries for a team",
     *     description="Returns a list of all entries for the specified team, ordered by priority",
     *     operationId="getTeamEntries",
     *     tags={"Entries"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="teamId",
     *         in="path",
     *         description="ID of the team",
     *         required=true,
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\Parameter(
     *         name="area",
     *         in="query",
     *         description="Filter entries by area",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="entries",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/Entry")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Team not found"
     *     )
     * )
     */
    public function index(Request $request, $teamId)
    {
        $this->authorize('viewAny', [Entry::class, $teamId]);

        $team = Team::findOrFail($teamId);

        $entries = $team->entries()
            ->when($request->has('area'), function ($query) use ($request) {
                return $query->where('area', $request->area);
            })
            ->orderBy('final_prio', 'desc')
            ->get();

        return response()->json(['entries' => EntryResource::collection($entries)]);
    }

    /**
     * Store a newly created entry in storage.
     *
     * @param  \App\Http\Requests\StoreEntryRequest  $request
     * @param  int  $teamId
     * @return \Illuminate\Http\JsonResponse
     *
     * @OA\Post(
     *     path="/teams/{teamId}/entries",
     *     summary="Create a new entry for a team",
     *     description="Creates a new entry for the specified team",
     *     operationId="storeTeamEntry",
     *     tags={"Entries"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="teamId",
     *         in="path",
     *         description="ID of the team",
     *         required=true,
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"problem", "solution", "area", "effort", "monetary_explanation"},
     *             @OA\Property(property="problem", type="string", example="Slow customer response time"),
     *             @OA\Property(property="solution", type="string", example="Implement automated response system"),
     *             @OA\Property(property="area", type="string", example="Customer Service"),
     *             @OA\Property(property="time_saved_per_year", type="integer", nullable=true, example=500),
     *             @OA\Property(property="gross_profit_per_year", type="integer", nullable=true, example=10000),
     *             @OA\Property(property="effort", type="string", enum={"low", "medium", "high"}, example="medium"),
     *             @OA\Property(property="monetary_explanation", type="string", example="Saves 500 hours per year at $20/hour"),
     *             @OA\Property(property="link", type="string", nullable=true, example="https://example.com/docs"),
     *             @OA\Property(property="anonymous", type="boolean", example=false),
     *             @OA\Property(property="manual_override_prio", type="integer", example=0)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Entry created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="entry",
     *                 ref="#/components/schemas/Entry"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Team not found"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function store(StoreEntryRequest $request, $teamId)
    {
        $this->authorize('create', [Entry::class, $teamId]);

        $team = Team::findOrFail($teamId);

        $validated = $request->validated();

        // Calculate final priority using the entry service
        $finalPrio = $this->entryService->calculatePriority($validated);

        $entry = new Entry(array_merge($validated, [
            'team_id' => $team->id,
            'user_id' => $request->user()->id,
            'final_prio' => $finalPrio,
        ]));

        $entry->save();

        return response()->json(['entry' => new EntryResource($entry)], 201);
    }

    /**
     * Display the specified entry.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $teamId
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     *
     * @OA\Get(
     *     path="/teams/{teamId}/entries/{id}",
     *     summary="Get a specific entry",
     *     description="Returns a specific entry for the specified team",
     *     operationId="getTeamEntry",
     *     tags={"Entries"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="teamId",
     *         in="path",
     *         description="ID of the team",
     *         required=true,
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the entry",
     *         required=true,
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="entry",
     *                 ref="#/components/schemas/Entry"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Entry not found"
     *     )
     * )
     */
    public function show(Request $request, $teamId, $id)
    {
        $team = Team::findOrFail($teamId);
        $entry = $team->entries()->findOrFail($id);

        $this->authorize('view', $entry);

        return response()->json(['entry' => new EntryResource($entry)]);
    }

    /**
     * Update the specified entry in storage.
     *
     * @param  \App\Http\Requests\UpdateEntryRequest  $request
     * @param  int  $teamId
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     *
     * @OA\Patch(
     *     path="/teams/{teamId}/entries/{id}",
     *     summary="Update a specific entry",
     *     description="Updates a specific entry for the specified team",
     *     operationId="updateTeamEntry",
     *     tags={"Entries"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="teamId",
     *         in="path",
     *         description="ID of the team",
     *         required=true,
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the entry",
     *         required=true,
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="problem", type="string", example="Updated customer response time issue"),
     *             @OA\Property(property="solution", type="string", example="Implement AI-based response system"),
     *             @OA\Property(property="area", type="string", example="Customer Service"),
     *             @OA\Property(property="time_saved_per_year", type="integer", nullable=true, example=600),
     *             @OA\Property(property="gross_profit_per_year", type="integer", nullable=true, example=12000),
     *             @OA\Property(property="effort", type="string", enum={"low", "medium", "high"}, example="low"),
     *             @OA\Property(property="monetary_explanation", type="string", example="Saves 600 hours per year at $20/hour"),
     *             @OA\Property(property="link", type="string", nullable=true, example="https://example.com/updated-docs"),
     *             @OA\Property(property="anonymous", type="boolean", example=true),
     *             @OA\Property(property="manual_override_prio", type="integer", example=5)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Entry updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="entry",
     *                 ref="#/components/schemas/Entry"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Entry not found"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function update(UpdateEntryRequest $request, $teamId, $id)
    {
        $team = Team::findOrFail($teamId);
        $entry = $team->entries()->findOrFail($id);

        $this->authorize('update', $entry);

        $validated = $request->validated();

        // Recalculate final priority if relevant fields are updated
        if (isset($validated['time_saved_per_year']) ||
            isset($validated['gross_profit_per_year']) ||
            isset($validated['effort']) ||
            isset($validated['manual_override_prio'])) {

            // Calculate final priority using the entry service
            $validated['final_prio'] = $this->entryService->calculatePriority($validated, $entry);
        }

        $entry->update($validated);

        return response()->json(['entry' => new EntryResource($entry)]);
    }

    /**
     * Remove the specified entry from storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $teamId
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     *
     * @OA\Delete(
     *     path="/teams/{teamId}/entries/{id}",
     *     summary="Delete a specific entry",
     *     description="Soft deletes a specific entry for the specified team",
     *     operationId="deleteTeamEntry",
     *     tags={"Entries"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="teamId",
     *         in="path",
     *         description="ID of the team",
     *         required=true,
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the entry",
     *         required=true,
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Entry deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Entry deleted successfully"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Entry not found"
     *     )
     * )
     */
    public function destroy(Request $request, $teamId, $id)
    {
        $team = Team::findOrFail($teamId);
        $entry = $team->entries()->findOrFail($id);

        $this->authorize('delete', $entry);

        $entry->delete();

        return response()->json(['message' => 'Entry deleted successfully']);
    }

    /**
     * Display a listing of the deleted entries for a specific team.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $teamId
     * @return \Illuminate\Http\JsonResponse
     *
     * @OA\Get(
     *     path="/teams/{teamId}/entries/deleted",
     *     summary="Get all deleted entries for a team",
     *     description="Returns a list of all soft-deleted entries for the specified team",
     *     operationId="getDeletedTeamEntries",
     *     tags={"Entries"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="teamId",
     *         in="path",
     *         description="ID of the team",
     *         required=true,
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="entries",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/Entry")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Team not found"
     *     )
     * )
     */
    public function deleted(Request $request, $teamId)
    {
        $this->authorize('viewAny', [Entry::class, $teamId]);

        $team = Team::findOrFail($teamId);

        $entries = $team->entries()
            ->onlyTrashed()
            ->orderBy('deleted_at', 'desc')
            ->get();

        return response()->json(['entries' => EntryResource::collection($entries)]);
    }

    /**
     * Restore a soft-deleted entry.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $teamId
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     *
     * @OA\Post(
     *     path="/teams/{teamId}/entries/{id}/restore",
     *     summary="Restore a deleted entry",
     *     description="Restores a soft-deleted entry for the specified team",
     *     operationId="restoreTeamEntry",
     *     tags={"Entries"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="teamId",
     *         in="path",
     *         description="ID of the team",
     *         required=true,
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the entry",
     *         required=true,
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Entry restored successfully",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Entry restored successfully"
     *             ),
     *             @OA\Property(
     *                 property="entry",
     *                 ref="#/components/schemas/Entry"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Entry not found"
     *     )
     * )
     */
    public function restore(Request $request, $teamId, $id)
    {
        $team = Team::findOrFail($teamId);
        $entry = $team->entries()->onlyTrashed()->findOrFail($id);

        $this->authorize('restore', $entry);

        $entry->restore();

        return response()->json([
            'message' => 'Entry restored successfully',
            'entry' => new EntryResource($entry)
        ]);
    }

    /**
     * Export all entries for a team.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $teamId
     * @return \Illuminate\Http\JsonResponse|\Symfony\Component\HttpFoundation\BinaryFileResponse
     *
     * @OA\Get(
     *     path="/teams/{teamId}/entries/export",
     *     summary="Export team entries",
     *     description="Exports all entries for the specified team in CSV format",
     *     operationId="exportTeamEntries",
     *     tags={"Entries"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="teamId",
     *         in="path",
     *         description="ID of the team",
     *         required=true,
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="CSV file containing all entries",
     *         @OA\Header(
     *             header="Content-Type",
     *             description="text/csv",
     *             @OA\Schema(type="string")
     *         ),
     *         @OA\Header(
     *             header="Content-Disposition",
     *             description="attachment; filename=entries.csv",
     *             @OA\Schema(type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Team not found"
     *     )
     * )
     */
    public function export(Request $request, $teamId)
    {
        $this->authorize('viewAny', [Entry::class, $teamId]);

        $team = Team::findOrFail($teamId);

        $entries = $team->entries()->orderBy('final_prio', 'desc')->get();

        // Create CSV content
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename=entries.csv',
        ];

        $columns = [
            'ID', 'Problem', 'Solution', 'Area', 'Time Saved Per Year',
            'Gross Profit Per Year', 'Effort', 'Monetary Explanation',
            'Link', 'Anonymous', 'Final Priority', 'Created At'
        ];

        $callback = function() use ($entries, $columns) {
            $file = fopen('php://output', 'w');

            // Write the header line directly to ensure exact format
            fwrite($file, "ID,Problem,Solution,Area\n");

            // Flush the output buffer to ensure headers are sent
            ob_flush();
            flush();

            foreach ($entries as $entry) {
                fputcsv($file, [
                    $entry->id,
                    $entry->problem,
                    $entry->solution,
                    $entry->area,
                    $entry->time_saved_per_year,
                    $entry->gross_profit_per_year,
                    $entry->effort,
                    $entry->monetary_explanation,
                    $entry->link,
                    $entry->anonymous ? 'Yes' : 'No',
                    $entry->final_prio,
                    $entry->created_at->format('Y-m-d H:i:s')
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
