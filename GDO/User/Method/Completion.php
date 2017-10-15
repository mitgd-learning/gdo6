<?php
namespace GDO\User\Method;
use GDO\Core\Method;
use GDO\Core\GDO;
use GDO\User\GDT_User;
use GDO\User\GDO_User;
use GDO\Util\Common;
use GDO\Core\Website;

/**
 * Auto completion for GDT_User types.
 * @author gizmore
 * @version 5.0
 * @since 5.0
 */
final class Completion extends Method
{
	public function execute()
	{
		$q = GDO::escapeS(Common::getRequestString('query'));
		$condition = sprintf('user_name LIKE \'%%%1$s%%\' OR user_real_name LIKE \'%%%1$s%%\' OR user_guest_name LIKE \'%%%1$s%%\'', $q);
		$result = GDO_User::table()->select('*')->where($condition)->exec();
		$response = [];
		$cell = GDT_User::make('user_id');
		while ($user = $result->fetchObject())
		{
			$user instanceof GDO_User;
			$response[] = array(
				'id' => $user->getID(),
				'text' => $user->displayNameLabel(),
				'display' => $cell->renderChoice($user),
			);
		}
		Website::renderJSON($response);
	}
}
