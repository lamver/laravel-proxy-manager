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
            'ip' => 'required|ip',
            'port' => 'required|integer|between:1,65535',
            'type' => 'required|in:http,https,socks4,socks5',
            'username' => 'nullable|string|max:255',
            'password' => 'nullable|string|max:255',
            'ip_port' => [
                function ($attribute, $value, $fail) use ($request) {
                    $exists = Proxy::where('ip', $request->ip)
                        ->where('port', $request->port)
                        ->exists();
                    if ($exists) {
                        $fail('Этот прокси-сервер (IP и Порт) уже добавлен в список.');
                    }
                }
            ]
        ]);

        $proxy = Proxy::create($validated);
        
        $this->proxyChecker->check($proxy);

        return response()->json($proxy, 201);
    }

    public function update(Request $request, Proxy $proxy)
    {
        $validated = $request->validate([
            'ip' => 'required|ip',
            'port' => 'required|integer|between:1,65535',
            'type' => 'required|in:http,https,socks4,socks5',
            'username' => 'nullable|string|max:255',
            'password' => 'nullable|string|max:255',
            // Проверка уникальности при изменении, исключая текущую запись
            'ip_port' => [
                function ($attribute, $value, $fail) use ($request, $proxy) {
                    $exists = Proxy::where('ip', $request->ip)
                        ->where('port', $request->port)
                        ->where('id', '!=', $proxy->id)
                        ->exists();
                    if ($exists) {
                        $fail('Другой прокси-сервер с такими IP и Портом уже существует.');
                    }
                }
            ]
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