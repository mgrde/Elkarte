<?php

/**
 * TestCase class for managing members
 *
 * Functional testing of web pages requests
 *
 * @backupGlobals disabled
 */
class TestManageMembers_Controller extends ElkArteWebTest
{
	public function registerMembers()
	{
		require_once(SUBSDIR . '/Members.subs.php');
		$require = array('activation', 'approval');
		$_SESSION['just_registered'] = 0;
		for ($i = 0; $i < 2; $i++)
		{
			$regOptions = array(
				'interface' => 'admin',
				'username' => 'user' . $i,
				'email' => 'user' . $i . '@mydomain.com',
				'password' => 'user' . $i,
				'password_check' => 'user' . $i,
				'require' => $require[$i],
				'memberGroup' => 2,
			);
			$memberID = registerMember($regOptions);
			$_SESSION['just_registered'] = 0;
		}
	}

	public function activateMember($mname, $act, $el)
	{
		// First, navigate to member management.
		$this->url('index.php?action=admin;area=viewmembers;sa=browse;type=' . $act);
		$this->assertEquals('Manage Members', $this->title());

		// Let's do it: approve/delete...
		$this->assertContains('new accounts', $this->byCssSelector('.additional_row a:last-child')->text());
		$this->clickit('.additional_row a:last-child');
		$this->assertContains($mname, $this->byCssSelector('#list_approve_list_0')->text());
		$this->clickit('#list_approve_list_0 input');
		$this->clickit('select[name=todo] option[value=' . $el . ']');
		$this->assertContains('all selected members?', $this->alertText());
		$this->acceptAlert();
	}

	public function testApproveMember()
	{
		// First, we register some members...
		$this->registerMembers();

		// Then, navigate to member management.
		$this->activateMember('user1', 'approve', 'ok');

		// Finally, ensure they have been approved.
		$this->url('index.php?action=admin;area=viewmembers;sa=search');
		$this->byId('activated-1')->click();
		$this->byId('activated-2')->click();
		$this->clickit('input[value=Search]');
		$this->assertContains('user1', $this->byId('member_list')->text());
	}

	public function testActivateMember()
	{
		$this->activateMember('user0', 'activate', 'delete');

		// Should be gone.
		$this->url('index.php?action=admin;area=viewmembers;sa=all');
		$this->assertNotContains('user0', $this->byId('member_list')->text());
	}
}
