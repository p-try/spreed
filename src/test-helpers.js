/*
 * @copyright Copyright (c) 2019 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
 *
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'

// helpers
const findNcActionButton = function(wrapper, text) {
	const actionButtons = wrapper.findAllComponents(NcActionButton)
	const items = actionButtons.filter(actionButton => {
		return actionButton.text() === text
	})
	if (!items.exists()) {
		return items
	}
	return items.at(0)
}

const findNcButton = function(wrapper, text) {
	const buttons = wrapper.findAllComponents(NcButton)
	const items = buttons.filter(button => {
		return button.text() === text || button.vm.ariaLabel === text
	})
	if (!items.exists()) {
		return items
	}
	return items.at(0)
}

export {
	findNcActionButton,
	findNcButton,
}
