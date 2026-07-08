<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Proxy;
use App\Services\ProxyCheckerService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProxyController extends Controller
{
    protected ProxyCheckerService $proxyChecker;

    public function __construct(ProxyCheckerService $proxyChecker)
    {
        $this->proxyChecker = $proxyChecker;
    }

    public function index()
    {
        return response()->json(Proxy::orderBy('id', 'desc')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
        'ip' => [
            'required',
            'ip',
            // Проверяем уникальность пары IP + Порт
            Rule::unique('proxies')->where(function ($query) use ($request) {
                return $query->where('port', $request->port);
            }),
        ],
        'port' => 'required|integer|between:1,65535',
        'type' => 'required|in:http,https,socks4,socks5',
        'username' => 'nullable|string|max:255',
        'password' => 'nullable|string|max:255',
        ], [
            // Кастомное сообщение об ошибке для фронтенда
            'ip.unique' => 'This proxy server (IP and Port) has already been added to the list.',
        ]);

        $proxy = Proxy::create($validated);
        
        $this->proxyChecker->check($proxy);

        return response()->json($proxy, 201);
    }

    public function update(Request $request, Proxy $proxy)
    {
        $validated = $request->validate([
        'ip' => [
            'required',
            'ip',
            // Проверяем уникальность пары при обновлении, игнорируя текущую запись
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
        $this->proxyChecker->check($proxy);

        return response()->json($proxy);
    }

    public function destroy(Proxy $proxy)
    {
        $proxy->delete();
        return response()->json(null, 204);
    }

    public function check(Proxy $proxy)
    {
        $this->proxyChecker->check($proxy);
        return response()->json($proxy);
    }
}