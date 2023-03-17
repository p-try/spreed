<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2016 Lukas Reschke <lukas@statuscode.ch>
 * @copyright Copyright (c) 2016 Joas Schilling <coding@schilljs.com>
 *
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Kate Döen <kate.doeen@nextcloud.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Talk\Controller;

use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\Session;
use OCA\Talk\Participant;
use OCA\Talk\ResponseDefinitions;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Service\RoomService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IRequest;
use OCP\IUserManager;

/**
 * @psalm-import-type SpreedCallPeer from ResponseDefinitions
 */
class CallController extends AEnvironmentAwareController {
	private ParticipantService $participantService;
	private RoomService $roomService;
	private IUserManager $userManager;
	private ITimeFactory $timeFactory;

	public function __construct(string $appName,
								IRequest $request,
								ParticipantService $participantService,
								RoomService $roomService,
								IUserManager $userManager,
								ITimeFactory $timeFactory) {
		parent::__construct($appName, $request);
		$this->participantService = $participantService;
		$this->roomService = $roomService;
		$this->userManager = $userManager;
		$this->timeFactory = $timeFactory;
	}

	/**
	 * @PublicPage
	 * @RequireCallEnabled
	 * @RequireParticipant
	 * @RequireReadWriteConversation
	 * @RequireModeratorOrNoLobby
	 *
	 * Get the peers for a call
	 *
	 * @return DataResponse<SpreedCallPeer[], Http::STATUS_OK>
	 */
	public function getPeersForCall(): DataResponse {
		$timeout = $this->timeFactory->getTime() - Session::SESSION_TIMEOUT;
		$result = [];
		$participants = $this->participantService->getParticipantsInCall($this->room, $timeout);

		foreach ($participants as $participant) {
			$displayName = $participant->getAttendee()->getActorId();
			if ($participant->getAttendee()->getActorType() === Attendee::ACTOR_USERS) {
				if ($participant->getAttendee()->getDisplayName()) {
					$displayName = $participant->getAttendee()->getDisplayName();
				} else {
					$userDisplayName = $this->userManager->getDisplayName($participant->getAttendee()->getActorId());
					if ($userDisplayName !== null) {
						$displayName = $userDisplayName;
					}
				}
			} else {
				$displayName = $participant->getAttendee()->getDisplayName();
			}

			$result[] = [
				'actorType' => $participant->getAttendee()->getActorType(),
				'actorId' => $participant->getAttendee()->getActorId(),
				'displayName' => $displayName,
				'token' => $this->room->getToken(),
				'lastPing' => $participant->getSession()->getLastPing(),
				'sessionId' => $participant->getSession()->getSessionId(),
			];
		}

		return new DataResponse($result);
	}

	/**
	 * @PublicPage
	 * @RequireCallEnabled
	 * @RequireParticipant
	 * @RequireReadWriteConversation
	 * @RequireModeratorOrNoLobby
	 *
	 * Join a call
	 *
	 * @param int|null $flags In-Call flags
	 * @param int|null $forcePermissions In-call permissions
	 * @param bool $silent Join the call silently
	 * @return DataResponse<array, Http::STATUS_OK|Http::STATUS_NOT_FOUND>
	 *
	 * 200: Call joined successfully
	 * 404: Call not found
	 */
	public function joinCall(?int $flags = null, ?int $forcePermissions = null, bool $silent = false): DataResponse {
		$this->participantService->ensureOneToOneRoomIsFilled($this->room);

		$session = $this->participant->getSession();
		if (!$session instanceof Session) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		if ($flags === null) {
			// Default flags: user is in room with audio/video.
			$flags = Participant::FLAG_IN_CALL | Participant::FLAG_WITH_AUDIO | Participant::FLAG_WITH_VIDEO;
		}

		if ($forcePermissions !== null && $this->participant->hasModeratorPermissions()) {
			$this->roomService->setPermissions($this->room, 'call', Attendee::PERMISSIONS_MODIFY_SET, $forcePermissions, true);
		}

		$this->participantService->changeInCall($this->room, $this->participant, $flags, false, $silent);

		return new DataResponse();
	}

	/**
	 * @PublicPage
	 * @RequireCallEnabled
	 * @RequireParticipant
	 * @RequirePermissions(permissions=call-start)
	 *
	 * Ring an attendee
	 *
	 * @param int $attendeeId ID of the attendee to ring
	 * @return DataResponse<array, Http::STATUS_OK|Http::STATUS_BAD_REQUEST>
	 *
	 * 200: Attendee rang successfully
	 * 400: Ringing attendee is not possible
	 */
	public function ringAttendee(int $attendeeId): DataResponse {
		if ($this->room->getCallFlag() === Participant::FLAG_DISCONNECTED) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		if ($this->participant->getSession() && $this->participant->getSession()->getInCall() === Participant::FLAG_DISCONNECTED) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		if (!$this->participantService->sendCallNotificationForAttendee($this->room, $this->participant, $attendeeId)) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		return new DataResponse();
	}

	/**
	 * @PublicPage
	 * @RequireParticipant
	 *
	 * Update the in-call flags
	 *
	 * @param int $flags New flags
	 * @return DataResponse<array, Http::STATUS_OK|Http::STATUS_BAD_REQUEST|Http::STATUS_NOT_FOUND>
	 *
	 * 200: In-call flags updated successfully
	 * 400: Updating in-call flags is not possible
	 * 404: Call session not found
	 */
	public function updateCallFlags(int $flags): DataResponse {
		$session = $this->participant->getSession();
		if (!$session instanceof Session) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		try {
			$this->participantService->updateCallFlags($this->room, $this->participant, $flags);
		} catch (\Exception $exception) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		return new DataResponse();
	}

	/**
	 * @PublicPage
	 * @RequireParticipant
	 *
	 * Leave a call
	 *
	 * @param bool $all whether to also terminate the call for all participants
	 * @return DataResponse<array, Http::STATUS_OK|Http::STATUS_NOT_FOUND>
	 *
	 * 200: Call left successfully
	 * 404: Call session not found
	 */
	public function leaveCall(bool $all = false): DataResponse {
		$session = $this->participant->getSession();
		if (!$session instanceof Session) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		if ($all && $this->participant->hasModeratorPermissions()) {
			$this->participantService->endCallForEveryone($this->room, $this->participant);
		} else {
			$this->participantService->changeInCall($this->room, $this->participant, Participant::FLAG_DISCONNECTED);
		}

		return new DataResponse();
	}
}
