<?php

namespace Stimata\Portal;

use Exception;

class StimataClient
{
    private $clientId;
    private $clientSecret;
    private $redirectUri;
    private $baseUrl;
    private $scopes;

    public function __construct(array $config)
    {
        $this->clientId = $config["client_id"] ?? "";
        $this->clientSecret = $config["client_secret"] ?? "";
        $this->redirectUri = $config["redirect_uri"] ?? "";
        $this->baseUrl = rtrim(
            $config["base_url"] ?? "http://localhost:9091/api",
            "/",
        );
        $this->scopes = $config["scopes"] ?? ["openid", "profile", "email"];

        if (empty($this->clientId) || empty($this->clientSecret)) {
            throw new Exception("ClientID and ClientSecret are required.");
        }
    }

    /**
     * 1. Initial Login: Generate Authorization URL
     * Returns array ['url' => '...', 'state' => '...']
     */
    public function getAuthUrl($state = null)
    {
        if (!$state) {
            $state = bin2hex(random_bytes(16));
        }

        $query = http_build_query([
            "client_id" => $this->clientId,
            "redirect_uri" => $this->redirectUri,
            "response_type" => "code",
            "scope" => implode(" ", $this->scopes),
            "state" => $state,
        ]);

        return [
            "url" => "$this->baseUrl/oauth/authorize?$query",
            "state" => $state,
        ];
    }

    /**
     * 2. Handle Callback: Automatically extracts code and state from URL
     * @param string $currentUrl The full URL of the current request
     * @param string $expectedState The state saved in session during login
     */
    public function handleCallback($currentUrl, $expectedState)
    {
        $parts = parse_url($currentUrl);
        if (!isset($parts["query"])) {
            throw new Exception("No query parameters found in URL");
        }

        parse_str($parts["query"], $query);

        if (isset($query["error"])) {
            throw new Exception(
                "OAuth Error: " .
                    ($query["error_description"] ?? $query["error"]),
            );
        }

        if (!isset($query["code"])) {
            throw new Exception("Missing 'code' parameter in callback URL");
        }

        if (!isset($query["state"]) || $query["state"] !== $expectedState) {
            throw new Exception("Invalid state parameter (potential CSRF)");
        }

        return $this->exchangeCode($query["code"]);
    }

    /**
     * Internal: Exchange Authorization Code for Token
     */
    private function exchangeCode($code)
    {
        return $this->request("POST", "/oauth/token", [
            "grant_type" => "authorization_code",
            "code" => $code,
            "redirect_uri" => $this->redirectUri,
            "client_id" => $this->clientId,
            "client_secret" => $this->clientSecret,
        ]);
    }

    /**
     * 3. Get Token with Client Credentials (Machine-to-Machine)
     */
    public function getTokenWithClientCredentials($scope = null)
    {
        $params = [
            "grant_type" => "client_credentials",
            "client_id" => $this->clientId,
            "client_secret" => $this->clientSecret,
        ];

        if ($scope !== null) {
            $params["scope"] = is_array($scope) ? implode(" ", $scope) : $scope;
        }

        return $this->request("POST", "/oauth/token", $params);
    }

    /**
     * 4. Refresh Token
     */
    public function refreshToken($refreshToken)
    {
        return $this->request("POST", "/oauth/token", [
            "grant_type" => "refresh_token",
            "refresh_token" => $refreshToken,
            "client_id" => $this->clientId,
            "client_secret" => $this->clientSecret,
        ]);
    }

    /**
     * 5. Introspect Token (Check validity)
     */
    public function introspect($token)
    {
        return $this->request("POST", "/oauth/introspect", [
            "token" => $token,
            "client_id" => $this->clientId,
            "client_secret" => $this->clientSecret,
        ]);
    }

    /**
     * 6. Revoke Token (Logout)
     */
    public function revoke($token)
    {
        try {
            $this->request("POST", "/oauth/revoke", [
                "token" => $token,
                "client_id" => $this->clientId,
                "client_secret" => $this->clientSecret,
            ]);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 7. Get User Profile
     */
    public function getUser($accessToken)
    {
        $response = $this->request(
            "GET",
            "/auth/me",
            [],
            ["Authorization: Bearer $accessToken"],
        );

        if (empty($response["success"]) || empty($response["data"])) {
            throw new Exception(
                $response["error"] ?? "Failed to get user profile",
            );
        }

        return $response["data"];
    }

    /**
     * 8. Switch Role
     */
    public function switchRole($accessToken, $role)
    {
        $url = "$this->baseUrl/oauth/switch-role";
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(["role" => $role]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "Accept: application/json",
            "Authorization: Bearer $accessToken",
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            throw new Exception("Request Error: " . curl_error($ch));
        }

        curl_close($ch);

        $data = json_decode($response, true);

        if ($httpCode >= 400) {
            $errorMsg =
                $data["error_description"] ??
                ($data["error"] ?? "Request failed with status " . $httpCode);
            throw new Exception($errorMsg, $httpCode);
        }

        return $data;
    }

    /**
     * 9. Check Access to Resource
     */
    public function checkAccess($accessToken, $resource)
    {
        $data = $this->request(
            "GET",
            "/v1/check-access",
            ["resource" => $resource],
            ["Authorization: Bearer " . $accessToken],
        );

        if (empty($data["success"]) || !isset($data["data"]["allowed"])) {
            throw new Exception($data["error"] ?? "Failed to check access");
        }

        return $data["data"]["allowed"];
    }

    /**
     * Internal Request Helper (Uses cURL)
     */
    private function request($method, $endpoint, $params = [], $headers = [])
    {
        $url = $this->baseUrl . $endpoint;
        $ch = curl_init();

        if ($method === "POST") {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
            $headers[] = "Content-Type: application/x-www-form-urlencoded";
        } else {
            if (!empty($params)) {
                $url .= "?" . http_build_query($params);
            }
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt(
            $ch,
            CURLOPT_HTTPHEADER,
            array_merge(["Accept: application/json"], $headers),
        );
        // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); // Enable in production

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            throw new Exception("Request Error: " . curl_error($ch));
        }

        curl_close($ch);

        $data = json_decode($response, true);

        if ($httpCode >= 400) {
            $errorMsg =
                $data["error_description"] ??
                ($data["error"] ?? "Request failed with status " . $httpCode);
            throw new Exception($errorMsg, $httpCode);
        }

        return $data;
    }
}
