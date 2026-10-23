<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Analytics;

use DateTimeImmutable;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Reads a GA4 property through the Data API (runReport) as a service account given Viewer on the property: the token
 * comes from a JWT signed with the account's key, so no Google library is needed. Used once, to fill the days before
 * Umami (`app:events:import-ga4-visits`).
 */
final class GoogleAnalyticsClient
{
    private const string TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const string REPORT_URL = 'https://analyticsdata.googleapis.com/v1beta/properties/%s:runReport';

    private const string SCOPE = 'https://www.googleapis.com/auth/analytics.readonly';

    /** The most rows runReport returns at once */
    private const int PAGE_SIZE = 100000;

    private ?string $token = null;

    /**
     * @param array{client_email: string, private_key: string} $serviceAccount
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly array $serviceAccount,
        private readonly string $propertyId,
    ) {
    }

    /**
     * @param string $keyFile the service account's JSON key, as Google Cloud downloads it
     */
    public static function fromKeyFile(HttpClientInterface $httpClient, string $keyFile, string $propertyId): self
    {
        $key = is_file($keyFile) ? json_decode((string) file_get_contents($keyFile), true) : null;
        if (!\is_array($key) || !\is_string($key['client_email'] ?? null) || !\is_string($key['private_key'] ?? null)) {
            throw new InvalidArgumentException(\sprintf('"%s" is not the JSON key of a service account.', $keyFile));
        }

        return new self($httpClient, ['client_email' => $key['client_email'], 'private_key' => $key['private_key']], $propertyId);
    }

    /**
     * The users of each page whose path contains $pathContains on $day (in the property's time zone). Pages GA4 lumps
     * together when a day has too many come as "(other)".
     *
     * @return iterable<string, int> by path
     */
    public function pathVisitors(DateTimeImmutable $day, string $pathContains): iterable
    {
        $date = $day->format('Y-m-d');
        for ($offset = 0;; $offset += self::PAGE_SIZE) {
            $report = $this->httpClient->request('POST', \sprintf(self::REPORT_URL, $this->propertyId), [
                'auth_bearer' => $this->token(),
                'json' => [
                    'dateRanges' => [['startDate' => $date, 'endDate' => $date]],
                    'dimensions' => [['name' => 'pagePath']],
                    'metrics' => [['name' => 'totalUsers']],
                    'dimensionFilter' => ['filter' => [
                        'fieldName' => 'pagePath',
                        'stringFilter' => ['matchType' => 'CONTAINS', 'value' => $pathContains],
                    ]],
                    'limit' => self::PAGE_SIZE,
                    'offset' => $offset,
                ],
            ])->toArray();

            foreach ($report['rows'] ?? [] as $row) {
                yield (string) $row['dimensionValues'][0]['value'] => (int) $row['metricValues'][0]['value'];
            }

            if ($offset + self::PAGE_SIZE >= (int) ($report['rowCount'] ?? 0)) {
                return;
            }
        }
    }

    private function token(): string
    {
        return $this->token ??= (string) $this->httpClient->request('POST', self::TOKEN_URL, ['body' => [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $this->assertion(),
        ]])->toArray()['access_token'];
    }

    /**
     * The JWT Google trades for an access token (RS256, an hour long).
     */
    private function assertion(): string
    {
        $now = time();
        $unsigned = self::base64Url((string) json_encode(['alg' => 'RS256', 'typ' => 'JWT']))
            . '.' . self::base64Url((string) json_encode([
                'iss' => $this->serviceAccount['client_email'],
                'scope' => self::SCOPE,
                'aud' => self::TOKEN_URL,
                'iat' => $now,
                'exp' => $now + 3600,
            ]));

        if (!openssl_sign($unsigned, $signature, $this->serviceAccount['private_key'], \OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('The service account key could not sign the token request.');
        }

        return $unsigned . '.' . self::base64Url($signature);
    }

    private static function base64Url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
