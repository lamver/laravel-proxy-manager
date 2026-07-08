<?php

namespace App\Http\Controllers\Api;

use App\Jobs\CheckProxyJob;
use App\Http\Controllers\Controller;
use App\Models\Proxy;
use App\Services\ProxyCheckerService;
use App\Services\ProxyImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;


/**
 * ProxyController class
 */
class ProxyController extends Controller
{
    protected ProxyCheckerService $proxyChecker;

    /**
     * ProxyController __construct function
     *
     * @param ProxyCheckerService $proxyChecker
     */
    public function __construct(ProxyCheckerService $proxyChecker)
    {
        $this->proxyChecker = $proxyChecker;
    }

    /**
     * index function
     *
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $query = Proxy::query();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('search')) {
            $query->where('ip', 'like', '%' . $request->search . '%');
        }

        $perPage = (int) $request->input('per_page', 15);

        if ($perPage < 1 || $perPage > 100) {
            $perPage = 15;
        }

        $proxies = $query->orderBy('id', 'desc')->paginate($perPage);

        return response()->json($proxies);
    }

    /**
     * store function
     *
     * @param Request $request
     * @return void
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ip' => [
                'required',
                'ip',
                Rule::unique('proxies')->where(function ($query) use ($request) {
                    return $query->where('port', $request->port);
                }),
            ],
            'port' => 'required|integer|between:1,65535',
            'type' => 'required|in:http,https,socks4,socks5',
            'username' => 'nullable|string|max:255',
            'password' => 'nullable|string|max:255',
        ], [
            'ip.unique' => 'This proxy server (IP and Port) has already been added to the list.',
        ]);

        $proxy = Proxy::create($validated);
        CheckProxyJob::dispatch($proxy);

        return response()->json($proxy, 201);
    }

    /**
     * update function
     *
     * @param Request $request
     * @param Proxy $proxy
     * @return JsonResponse
     */
    public function update(Request $request, Proxy $proxy): JsonResponse
    {
        $validated = $request->validate([
            'ip' => [
                'required',
                'ip',
                Rule::unique('proxies')->ignore($proxy->id)->where(function ($query) use ($request) {
                    return $query->where('port', $request->port);
                }),
            ],
            'port' => 'required|integer|between:1,65535',
            'type' => 'required|in:http,https,socks4,socks5',
            'username' => 'nullable|string|max:255',
            'password' => 'nullable|string|max:255',
        ], [
            'ip.unique' => 'Another proxy server with the same IP and Port already exists.',
        ]);

        $proxy->update($validated);
        CheckProxyJob::dispatch($proxy);

        return response()->json($proxy);
    }

    /**
     * destroy function
     *
     * @param Proxy $proxy
     * @return JsonResponse
     */
    public function destroy(Proxy $proxy): JsonResponse
    {
        $proxy->delete();
        return response()->json(null, 204);
    }

    /**
     * check function
     *
     * @param Proxy $proxy
     * @return JsonResponse
     */
    public function check(Proxy $proxy): JsonResponse
    {
        $this->proxyChecker->check($proxy);
        return response()->json($proxy);
    }

    /**
     * import function
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function import(Request $request, ProxyImportService $importService): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:2048',
        ]);

        $result = $importService->importFromTxt($request->file('file'));

        if ($result['imported'] === 0 && !empty($result['errors'])) {
            return response()->json([
                'message' => 'Не удалось импортировать прокси. Проверьте формат файла.',
                'errors' => ['file' => $result['errors']]
            ], 422);
        }

        return response()->json([
            'success'  => true,
            'imported' => $result['imported'],
            'skipped_errors' => $result['errors']
        ]);
    }
}
