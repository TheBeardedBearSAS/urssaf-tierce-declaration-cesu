<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Support;

use Http\Message\RequestMatcher\RequestMatcher;
use Http\Mock\Client;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CesuNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\UrssafEndpoints;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire\ApiPaths;

/**
 * Scripted URSSAF CESU platform behind the mocked PSR-18 client (reference HttpScript; lot 6 design §10.2). Every route
 * answers a success by default (the wire fixtures of tests/Fixtures/wire) and can be switched to a given status or to a
 * transport failure. Requests are counted per route.
 */
final class HttpScript
{
    public const ROUTE_AUTHENTICATION = 'authentication';
    public const ROUTE_010 = '010';
    public const ROUTE_020 = '020';
    public const ROUTE_021 = '021';
    public const ROUTE_030 = '030';
    public const ROUTE_031 = '031';
    public const ROUTE_040 = '040';
    public const ROUTE_050 = '050';
    public const ROUTE_060 = '060';
    public const ROUTE_070 = '070';

    private const SAMPLE_EMPLOYER = 'Z0003752390009';

    /** @var array<string, RouteEndpoint> */
    private array $routes;

    public function __construct(Client $client)
    {
        $this->routes = [
            self::ROUTE_AUTHENTICATION => new RouteEndpoint(200, self::fixture('token_response.json')),
            self::ROUTE_010 => new RouteEndpoint(200, self::fixture('register_employer_response.json')),
            self::ROUTE_020 => new RouteEndpoint(200, self::fixture('verify_employer_response.json')),
            self::ROUTE_021 => new RouteEndpoint(200, self::fixture('verify_employee_response.json')),
            self::ROUTE_030 => new RouteEndpoint(200, ''),
            self::ROUTE_031 => new RouteEndpoint(200, ''),
            self::ROUTE_040 => new RouteEndpoint(200, self::fixture('estimate_response.json')),
            self::ROUTE_050 => new RouteEndpoint(200, self::fixture('declare_response_assembled.json')),
            self::ROUTE_060 => new RouteEndpoint(200, self::fixture('activate_cesu_plus_response.json')),
            self::ROUTE_070 => new RouteEndpoint(200, self::fixture('activate_aici_response.json')),
        ];
        $employer = CesuNumber::fromString(self::SAMPLE_EMPLOYER);
        $matchers = [
            self::ROUTE_AUTHENTICATION => new RequestMatcher(self::path(UrssafEndpoints::TOKEN_PATH, ''), null, ['POST']),
            self::ROUTE_010 => new RequestMatcher(self::path(ApiPaths::registerEmployer()), null, ['POST']),
            self::ROUTE_020 => new RequestMatcher(self::path(ApiPaths::verifyEmployer($employer)), null, ['GET']),
            self::ROUTE_021 => new RequestMatcher(self::path(ApiPaths::verifyEmployee()), null, ['GET']),
            self::ROUTE_030 => new RequestMatcher(self::path(ApiPaths::mandates()), null, ['POST']),
            self::ROUTE_031 => new RequestMatcher(self::path(ApiPaths::mandates()), null, ['DELETE']),
            self::ROUTE_040 => new RequestMatcher(self::path(ApiPaths::estimate()), null, ['POST']),
            self::ROUTE_050 => new RequestMatcher(self::path(ApiPaths::declare($employer)), null, ['POST']),
            self::ROUTE_060 => new RequestMatcher(self::path(ApiPaths::activateCesuPlus($employer)), null, ['POST']),
            self::ROUTE_070 => new RequestMatcher(self::path(ApiPaths::activateAici($employer)), null, ['POST']),
        ];
        foreach ($matchers as $route => $matcher) {
            $client->on($matcher, $this->routes[$route]);
        }
    }

    /**
     * The content of a wire fixture of tests/Fixtures/wire.
     */
    public static function fixture(string $name): string
    {
        $content = file_get_contents(__DIR__ . '/../../../Fixtures/wire/' . $name);
        \assert(\is_string($content), 'Unreadable wire fixture ' . $name);

        return $content;
    }

    /**
     * The request path regular expression of a path of the API: the employer Cesu number matches any employer.
     */
    private static function path(string $path, string $base = UrssafEndpoints::API_BASE_PATH): string
    {
        $pattern = preg_quote($base . $path, '/');

        return str_replace(preg_quote(self::SAMPLE_EMPLOYER, '/'), '[^/]+', $pattern) . '$';
    }

    public function calls(string $route): int
    {
        return $this->routes[$route]->calls();
    }

    /**
     * @param array<string, string> $headers
     */
    public function respond(string $route, int $status, string $body, array $headers = []): void
    {
        $this->routes[$route]->respond($status, $body, $headers);
    }

    public function respondWithFixture(string $route, int $status, string $fixture): void
    {
        $this->routes[$route]->respond($status, self::fixture($fixture));
    }

    public function failTransport(string $route): void
    {
        $this->routes[$route]->failTransport();
    }
}
