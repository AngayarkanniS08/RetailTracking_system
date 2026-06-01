<?php

namespace Core;

use Modules\Auth\Controller\Api\ForgotPasswordController;
use Modules\Auth\Controller\Api\ResetPasswordController;
use Modules\Auth\Controller\Api\RegistrationController;
use Modules\Auth\Controller\Api\LoginController;
use Modules\Product\Controller\Api\CategoryController;
use Modules\Product\Controller\Api\SubcategoryController;
use Modules\Product\Controller\Api\ProductController;
use Modules\Product\Controller\Api\UnitController;

class ApiRoutes
{
    public static function register(Router $router): void
    {
        $router->add('POST', '/api/forgot-password', function (): void {
            (new ForgotPasswordController())->forgot();
        });

        $router->add('POST', '/api/reset-password', function (): void {
            (new ResetPasswordController())->reset();
        });

        $router->add('POST', '/api/register', function (): void {
            (new RegistrationController())->register();
        });

        $router->add('POST', '/api/login', function (): void {
            (new LoginController())->login();
        });

        $router->add('GET', '/api/categories', function (): void {
            (new CategoryController())->index();
        });
        $router->add('POST', '/api/categories', function (): void {
            (new CategoryController())->store();
        });
        $router->add('PUT', '/api/categories/{id}', function (array $params): void {
            (new CategoryController())->update($params['id']);
        });
        $router->add('DELETE', '/api/categories/{id}', function (array $params): void {
            (new CategoryController())->destroy($params['id']);
        });

        $router->add('GET', '/api/subcategories', function (): void {
            (new SubcategoryController())->index();
        });
        $router->add('POST', '/api/subcategories', function (): void {
            (new SubcategoryController())->store();
        });
        $router->add('PUT', '/api/subcategories/{id}', function (array $params): void {
            (new SubcategoryController())->update($params['id']);
        });
        $router->add('DELETE', '/api/subcategories/{id}', function (array $params): void {
            (new SubcategoryController())->destroy($params['id']);
        });

        $router->add('GET', '/api/products', function (): void {
            (new ProductController())->index();
        });
        $router->add('POST', '/api/products', function (): void {
            (new ProductController())->store();
        });
        $router->add('PUT', '/api/products/{id}', function (array $params): void {
            (new ProductController())->update($params['id']);
        });
        $router->add('DELETE', '/api/products/{id}', function (array $params): void {
            (new ProductController())->destroy($params['id']);
        });

        $router->add('GET', '/api/units', function (): void {
            (new UnitController())->index();
        });
    }
}
