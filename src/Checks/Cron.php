<?php

namespace AgoLab\Monitor\Checks;

defined( 'ABSPATH' ) || exit;

class Cron {

    /**
     * Summary check: total events, overdue count.
     *
     * @return array{status: string, label: string, total: int, overdue: int}
     */
    public static function check(): array {
        $events  = self::get_all_events();
        $overdue = 0;

        foreach ( $events as $event ) {
            if ( 'overdue' === $event['status'] ) {
                $overdue++;
            }
        }

        $status = $overdue > 0 ? 'warning' : 'ok';

        return [
            'status'  => $status,
            'label'   => __( 'Cron Jobs', 'ago-monitor' ),
            'total'   => count( $events ),
            'overdue' => $overdue,
        ];
    }

    /**
     * Get all scheduled cron events with status.
     *
     * @return array<int, array{hook: string, schedule: string, next_run: int, next_run_human: string, status: string}>
     */
    public static function get_all_events(): array {
        $crons = _get_cron_array();
        if ( empty( $crons ) ) {
            return [];
        }

        $now    = time();
        $events = [];

        foreach ( $crons as $timestamp => $hooks ) {
            foreach ( $hooks as $hook => $schedules ) {
                foreach ( $schedules as $key => $data ) {
                    $is_overdue = ( $timestamp < ( $now - 600 ) );

                    $events[] = [
                        'hook'           => $hook,
                        'schedule'       => $data['schedule'] ?: __( 'single', 'ago-monitor' ),
                        'next_run'       => (int) $timestamp,
                        'next_run_human' => human_time_diff( $timestamp, $now ),
                        'status'         => $is_overdue ? 'overdue' : 'ok',
                    ];
                }
            }
        }

        // Sort by next_run ascending.
        usort( $events, fn( $a, $b ) => $a['next_run'] <=> $b['next_run'] );

        return $events;
    }
}
