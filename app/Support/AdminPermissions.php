<?php

namespace App\Support;

final class AdminPermissions
{
    const ALL = ['settings.view', 'settings.manage', 'dashboard.view', 'reports.view', 'products.view', 'products.manage', 'billing.view', 'billing.manage', 'services.view', 'services.manage', 'customers.view', 'support.view', 'support.manage', 'coupons.view', 'coupons.manage', 'integrations.view', 'integrations.manage', 'operations.view', 'operations.manage', 'audit.view', 'team.manage', 'knowledge.view', 'knowledge.manage'];

    public static function forRoute(string $name, string $method = 'GET'): string
    {
        $module = explode('.', $name)[1] ?? '';
        $module = ['invoices' => 'billing', 'reviews' => 'billing', 'users' => 'customers', 'tickets' => 'support', 'connectors' => 'integrations'][$module] ?? $module;
        if ($module === 'team') {
            return 'team.manage';
        }

        return $module.'.'.(in_array($method, ['GET', 'HEAD']) ? 'view' : 'manage');
    }
}
