<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Infrastructure\WordPress\Rest;

use Cheppi\Sellers\Application\BusinessRulesRepository;
use Cheppi\Sellers\Application\UpdateBusinessRules;
use Cheppi\Sellers\Domain\Rules\RulesValidationException;
use Cheppi\Sellers\Infrastructure\WordPress\Capabilities;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * GET/PUT /wp-json/cheppi/v1/rules — canonical rules (rates in basis points).
 * Part of the API contract a future platform keeps.
 */
final class RulesController
{
    public const NAMESPACE = 'cheppi/v1';

    public function __construct(private readonly BusinessRulesRepository $repository)
    {
    }

    public function register(): void
    {
        register_rest_route(self::NAMESPACE, '/rules', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'show'],
                'permission_callback' => [$this, 'canManage'],
            ],
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'update'],
                'permission_callback' => [$this, 'canManage'],
            ],
        ]);
    }

    public function canManage(): bool
    {
        return current_user_can(Capabilities::MANAGE);
    }

    public function show(): WP_REST_Response
    {
        return new WP_REST_Response($this->repository->current()->toArray());
    }

    public function update(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $input = $request->get_json_params();
        if (!is_array($input)) {
            return new WP_Error('cheppi_invalid_body', __('Se esperaba un objeto JSON.', 'cheppi-sellers'), ['status' => 400]);
        }
        try {
            $rules = (new UpdateBusinessRules($this->repository))($input, get_current_user_id());
        } catch (RulesValidationException $e) {
            return new WP_Error('cheppi_invalid_rules', $e->getMessage(), ['status' => 422, 'errors' => $e->errors]);
        }
        return new WP_REST_Response($rules->toArray());
    }
}
