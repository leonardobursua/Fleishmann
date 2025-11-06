<?php
defined('BASEPATH') or exit('No direct script access allowed');
/*
Module Name: Custom Reports
Description: Informes per. (reportes personalizados) con permisos nativos+propios, etiquetas editables, filtros tipo Excel y exportación filtrada. Compat 3.3.1 (PHP 8.2)
Version: 3.4.4
*/

if (!function_exists('custom_reports_add_menu')) {
    function custom_reports_add_menu() {
        static $added = false;
        if ($added) { return; }
        $added = true;
        $CI = &get_instance();
        if (!get_staff_user_id()) { return; }

        $CI->app_menu->add_sidebar_menu_item('custom_reports', [
            'slug'     => 'custom_reports',
            'name'     => 'Informes per.',
            'icon'     => 'fa fa-bar-chart',
            'href'     => admin_url('custom_reports'),
            'position' => 12,
        ]);

        $CI->app_menu->add_sidebar_children_item('custom_reports', [
            'slug'     => 'custom_reports_reports',
            'name'     => 'Reportes',
            'icon'     => 'fa fa-table',
            'href'     => admin_url('custom_reports'),
            'position' => 1,
        ]);

        if (function_exists('is_admin') && is_admin()) {
            $CI->app_menu->add_sidebar_children_item('custom_reports', [
                'slug'     => 'custom_reports_permissions',
                'name'     => 'Permisos',
                'icon'     => 'fa fa-lock',
                'href'     => admin_url('custom_reports/permissions'),
                'position' => 2,
            ]);
            $CI->app_menu->add_sidebar_children_item('custom_reports', [
                'slug'     => 'custom_reports_fields',
                'name'     => 'Campos habilitados',
                'icon'     => 'fa fa-toggle-on',
                'href'     => admin_url('custom_reports/fields_matrix'),
                'position' => 3,
            ]);
        }
    }
}
hooks()->add_action('admin_init', 'custom_reports_add_menu');
hooks()->add_action('admin_head', 'custom_reports_add_menu');

hooks()->add_action('admin_init', function () {
    $CI = &get_instance();
    if (!$CI->db->table_exists(db_prefix().'customreport_permissions')) {
        $CI->db->query('CREATE TABLE IF NOT EXISTS `'.db_prefix().'customreport_permissions` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `role_id` INT(11) NOT NULL,
            `entity` VARCHAR(50) NOT NULL,
            `can_view_own` TINYINT(1) DEFAULT 0,
            `can_view_global` TINYINT(1) DEFAULT 0,
            `can_visualize` TINYINT(1) DEFAULT 0,
            PRIMARY KEY (`id`),
            UNIQUE KEY `role_entity_unique` (`role_id`, `entity`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;');
    }
    if (!$CI->db->table_exists(db_prefix().'customreport_field_permissions')) {
        $CI->db->query('CREATE TABLE IF NOT EXISTS `'.db_prefix().'customreport_field_permissions` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `entity` VARCHAR(50) NOT NULL,
            `field_key` VARCHAR(100) NOT NULL,
            `visible` TINYINT(1) DEFAULT 1,
            `label_custom` VARCHAR(255) NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `entity_field_unique` (`entity`, `field_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;');
    } else {
        $fields = $CI->db->list_fields(db_prefix().'customreport_field_permissions');
        if (!in_array('label_custom', $fields)) {
            $CI->db->query('ALTER TABLE `'.db_prefix().'customreport_field_permissions` ADD COLUMN `label_custom` VARCHAR(255) NULL AFTER `visible`;');
        }
    }
});
