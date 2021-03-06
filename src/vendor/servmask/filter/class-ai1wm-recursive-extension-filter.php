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

class Ai1wm_Recursive_Extension_Filter extends RecursiveFilterIterator {

	protected $include = array();

    /**
     * Ai1wm_Recursive_Extension_Filter constructor.
     *
     * @param \RecursiveIterator $iterator
     * @param array              $include
     */
    public function __construct( RecursiveIterator $iterator, $include = array() ) {
		parent::__construct( $iterator );

		// Set include filter
		$this->include = $include;
	}

    /**
     * @return bool
     */
    public function accept() {
        return ! ($this->getInnerIterator()->isFile() && ! in_array(pathinfo($this->getInnerIterator()->getFilename(),
                PATHINFO_EXTENSION), $this->include));
    }

    /**
     * @return \Ai1wm_Recursive_Extension_Filter|\RecursiveFilterIterator
     */
    public function getChildren() {
		return new self( $this->getInnerIterator()->getChildren(), $this->include );
	}
}
