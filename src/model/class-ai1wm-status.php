<?php
/**
 * Copyright (C) 2014-2018 ServMask Inc.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * ███████╗███████╗██████╗ ██╗   ██╗███╗   ███╗ █████╗ ███████╗██╗  ██╗
 * ██╔════╝██╔════╝██╔══██╗██║   ██║████╗ ████║██╔══██╗██╔════╝██║ ██╔╝
 * ███████╗█████╗  ██████╔╝██║   ██║██╔████╔██║███████║███████╗█████╔╝
 * ╚════██║██╔══╝  ██╔══██╗╚██╗ ██╔╝██║╚██╔╝██║██╔══██║╚════██║██╔═██╗
 * ███████║███████╗██║  ██║ ╚████╔╝ ██║ ╚═╝ ██║██║  ██║███████║██║  ██╗
 * ╚══════╝╚══════╝╚═╝  ╚═╝  ╚═══╝  ╚═╝     ╚═╝╚═╝  ╚═╝╚══════╝╚═╝  ╚═╝
 */

class Ai1wm_Status {

    /**
     * @param $title
     * @param $message
     */
    public static function error( $title, $message ) {
		self::log( array( 'type' => 'error', 'title' => $title, 'message' => $message ) );
	}

    /**
     * @param $message
     */
    public static function info( $message ) {
		self::log( array( 'type' => 'info', 'message' => $message ) );
	}

    /**
     * @param $message
     */
    public static function download( $message ) {
		self::log( array( 'type' => 'download', 'message' => $message ) );
	}

    /**
     * @param $message
     */
    public static function confirm( $message ) {
		self::log( array( 'type' => 'confirm', 'message' => $message ) );
	}

    /**
     * @param $title
     * @param $message
     */
    public static function done( $title, $message ) {
		self::log( array( 'type' => 'done', 'title' => $title, 'message' => $message ) );
	}

    /**
     * @param $title
     * @param $message
     */
    public static function blogs( $title, $message ) {
		self::log( array( 'type' => 'blogs', 'title' => $title, 'message' => $message ) );
	}

    /**
     * @param $percent
     */
    public static function progress( $percent ) {
		self::log( array( 'type' => 'progress', 'percent' => $percent ) );
	}

    /**
     * @param $data
     */
    public static function log( $data ) {
		if ( ! ai1wm_is_scheduled_backup() ) {
			update_option( AI1WM_STATUS, $data );
		}
	}
}
