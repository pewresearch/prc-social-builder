import { useCallback } from '@wordpress/element';
/**
 * Sends a browser (Firebase) notification when available, falling back to alert().
 * Also exposes a helper to subscribe the current user to browser notifications.
 */

export default function useSocialPackageAlert(
	packageTitle: string,
	userId: number
) {
	const firebaseMessaging =
		'function' === typeof window?.fcm?.getBrowserSubscription &&
		'function' === typeof window?.fcm?.restfullySendMessageToUsers &&
		'function' === typeof window?.fcm?.subscribeToBrowserNotifications &&
		'function' === typeof window?.fcm?.validateInstallation;
	const sendAlert = useCallback(
		async (success: boolean) => {
			const text = success
				? `SUCCESS: Created "Social Package for ${packageTitle}"`
				: `ERROR: Failed to create a social package for "${packageTitle}"`;
			if (firebaseMessaging && userId > 0) {
				const browserSubscribed =
					await window.fcm.getBrowserSubscription();
				const validInstallation =
					await window.fcm.validateInstallation();
				if (browserSubscribed && validInstallation) {
					await window.fcm.restfullySendMessageToUsers(
						[userId],
						text,
						'Social Package Builder',
						window.location.href
					);
					return;
				}
			}
			// eslint-disable-next-line no-alert
			alert(text);
		},
		[packageTitle, userId, firebaseMessaging]
	);

	const maybeAddFCMWorker = async () => {
		if (userId <= 0 || !firebaseMessaging) {
			return false;
		}
		const browserSubscribed = await window.fcm.getBrowserSubscription();
		const validInstallation = await window.fcm.validateInstallation();
		if (browserSubscribed && validInstallation) {
			return true;
		}
		return await window.fcm.subscribeToBrowserNotifications(userId);
	};
	return { sendAlert, maybeAddFCMWorker };
}
