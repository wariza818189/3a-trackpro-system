<?php

namespace Tests\Feature\Navigation;

use App\Models\User;
use Tests\Feature\Auth\AuthTestCase;

class ResponsiveNavigationTest extends AuthTestCase
{
    public function test_admin_navigation_uses_all_nine_destinations_in_desktop_and_mobile_menus(): void
    {
        $admin = User::factory()->admin()->create();
        $html = $this->actingAs($admin)->get(route('home'))->assertOk()->getContent();
        $expected = [
            'home' => 'Home',
            'pos.index' => 'POS',
            'sales.index' => 'Sales History',
            'categories.index' => 'Categories',
            'products.index' => 'Products',
            'product-variants.index' => 'Variants',
            'stock-in.index' => 'Stock In',
            'opening-inventory.index' => 'Opening Inventory',
            'stock-corrections.index' => 'Stock Correction',
        ];

        $this->assertNavigationDestinations($html, $expected);
    }

    public function test_staff_navigation_uses_exactly_the_seven_permitted_destinations_in_both_menus(): void
    {
        $staff = User::factory()->create();
        $html = $this->actingAs($staff)->get(route('home'))->assertOk()->getContent();
        $expected = [
            'home' => 'Home',
            'pos.index' => 'POS',
            'sales.index' => 'Sales History',
            'categories.index' => 'Categories',
            'products.index' => 'Products',
            'product-variants.index' => 'Variants',
            'stock-in.index' => 'Stock In',
        ];

        $this->assertNavigationDestinations($html, $expected);
        $this->assertStringNotContainsString('data-nav-route="opening-inventory.index"', $html);
        $this->assertStringNotContainsString('data-nav-route="stock-corrections.index"', $html);
        $this->assertStringNotContainsString('Opening Inventory', $html);
        $this->assertStringNotContainsString('Stock Correction', $html);
    }

    public function test_navigation_markup_is_accessible_print_hidden_active_and_uses_secure_logout_forms(): void
    {
        $admin = User::factory()->admin()->create();
        $html = $this->actingAs($admin)->get(route('home'))->assertOk()->getContent();

        $this->assertStringContainsString('data-nav-toggle', $html);
        $this->assertMatchesRegularExpression('/<button[^>]*type="button"[^>]*data-nav-toggle[^>]*aria-controls="mobile-navigation"[^>]*aria-expanded="false"[^>]*aria-label="Open navigation"/', $html);
        $this->assertStringContainsString('aria-controls="mobile-navigation"', $html);
        $this->assertStringContainsString('aria-expanded="false"', $html);
        $this->assertStringContainsString('id="mobile-navigation"', $html);
        $this->assertStringContainsString('data-nav-drawer', $html);
        $this->assertStringContainsString('aria-hidden="true"', $html);
        $this->assertMatchesRegularExpression('/<aside[^>]*data-nav-drawer[^>]*\sinert(?:\s|>)/', $html);
        $this->assertStringContainsString('data-nav-close', $html);
        $this->assertMatchesRegularExpression('/<button[^>]*type="button"[^>]*data-nav-close[^>]*aria-label="Close navigation"/', $html);
        $this->assertStringContainsString('data-nav-backdrop', $html);

        foreach (['aside[^>]*data-nav-sidebar', 'header[^>]*data-nav-mobile-bar', 'div[^>]*data-nav-backdrop', 'aside[^>]*data-nav-drawer'] as $surface) {
            $this->assertMatchesRegularExpression('/<'.$surface.'[^>]*class="[^"]*print:hidden[^"]*"/', $html);
        }
        $this->assertStringContainsString('lg:pl-60 print:pl-0', $html);
        $this->assertSame(2, preg_match_all('/data-nav-account="(?:desktop|mobile)"[^>]*class="[^"]*print:hidden[^"]*"/', $html));
        $this->assertSame(2, substr_count($html, 'data-nav-route="home"'));
        $this->assertSame(2, preg_match_all('/data-nav-route="home"[^>]*aria-current="page"/', $html));
        $this->assertSame(2, substr_count($html, 'data-nav-logout='));

        foreach (['desktop', 'mobile'] as $mode) {
            $form = $this->elementMarkup($html, 'form', 'data-nav-logout', $mode);
            $this->assertStringContainsString('method="POST"', $form);
            $this->assertStringContainsString('action="'.route('logout').'"', $form);
            $this->assertStringContainsString('name="_token"', $form);
        }
        $this->assertStringNotContainsString('<a href="'.route('logout').'"', $html);
    }

    /** @param array<string, string> $expected */
    private function assertNavigationDestinations(string $html, array $expected): void
    {
        foreach (['desktop', 'mobile'] as $mode) {
            $navigation = $this->elementMarkup($html, 'nav', 'data-nav-menu', $mode);
            $this->assertSame(count($expected), substr_count($navigation, 'data-nav-route='));

            foreach ($expected as $routeName => $label) {
                $this->assertStringContainsString('href="'.route($routeName).'"', $navigation);
                $this->assertStringContainsString('data-nav-route="'.$routeName.'"', $navigation);
                $this->assertStringContainsString($label, $navigation);
                if ($mode === 'mobile') {
                    $this->assertMatchesRegularExpression('/data-nav-route="'.preg_quote($routeName, '/').'"[^>]*data-nav-link/', $navigation);
                }
            }
        }
    }

    private function elementMarkup(string $html, string $element, string $attribute, string $value): string
    {
        $matched = preg_match(
            '/<'.$element.'[^>]*'.$attribute.'="'.preg_quote($value, '/').'"[^>]*>.*?<\/'.$element.'>/s',
            $html,
            $matches,
        );

        $this->assertSame(1, $matched, "The {$value} {$element} element was not found.");

        return $matches[0];
    }
}
