<?php

/**
 * Helper to return a "User" with the $password.
 */
function _user($password) {
    return array('password' => $password);
}


class UserModelTest extends UnitTestCase {

    function UserModelTest() {
        loadModel('User');
        $this->User = new User();
        // RUN THE QUERY, CAKE.
        $this->User->cacheQueries = False;
    }

    /* Make sure the helper works. */
    function test_user(){
        $password = 'yar';
        $user = _user($password);
        $this->assertEqual($user['password'], $password);
    }

    function testCheckPassword() {
        $this->assertFalse($this->User->checkPassword(_user(''), ''),
                           "Users with empty passwords never pass.");

        $raw = 'foo';
        $encrypted = $this->User->createPassword($raw);
        $this->assertTrue($this->User->checkPassword(_user($encrypted), $raw),
                          "Valid password works.");
        $this->assertFalse($this->User->checkPassword(_user($encrypted), 'bar'),
                           "Non-matching passwords don't pass.");

    }

    function test_md5Update() {

        // Check updating a user with an existing md5 password.
        $id = 555;
        $raw = 'foo';
        $md5password = md5($raw);
        $this->User->execute("INSERT INTO users (id, password)
                              VALUES ({$id}, '{$md5password}')");
        $newUser = $this->User->findById($id);

        $this->assertTrue($this->User->checkPassword($newUser['User'], $raw),
                           "The password checks out with md5.");

        // TODO: will caching be affected in production?
        $this->User->caching = False;
        $updatedUser = $this->User->findById($id);
        $newPassword = $updatedUser['User']['password'];
        $this->assertTrue($this->User->_checkPassword($raw, $newPassword),
                           "The old md5 password was updated.");

        $this->User->del($id);
    }

    function test_checkPassword() {
        $raw = 'foo';
        $encrypted = $this->User->createPassword($raw);
        $this->assertTrue($this->User->_checkPassword($raw, $encrypted));
        $this->assertFalse($this->User->_checkPassword('', $encrypted));
    }

    function testGetHexDigest() {
        $algo = 'sha512';
        $salt = 'foo';
        $password = 'bar';
        $this->assertEqual($this->User->getHexDigest($algo, $salt, $password),
                           hash($algo, $salt.$password));
    }

    function testCreatePassword() {
        $algo = 'sha512';
        $rawPassword = 'bla';
        $password = $this->User->createPassword($rawPassword);

        list($hashedAlgo, $hashedSalt, $hashedPassword) = split('\$', $password);
        $this->assertEqual($algo, $hashedAlgo);
        $this->assertEqual($this->User->getHexDigest($algo, $hashedSalt, $rawPassword),
                           $hashedPassword);
    }

    function testResetCode() {
        $id = 555;
        $this->User->execute("INSERT INTO users(id) VALUES ({$id})");

        $this->User->setResetCode($id);
        $user = $this->User->findById($id);

        $this->assertTrue(!empty($user['User']['resetcode']), "The resetcode was set");

        list($expire_date, ) = split(' ', $user['User']['resetcode_expires']);
        $expires = date('Y-m-d', strtotime(PASSWORD_RESET_EXPIRES.' days'));
        $this->assertTrue($expire_date == $expires,
                          'The resetcode expires in '.PASSWORD_RESET_EXPIRES.' days.');

        $this->assertTrue($this->User->checkResetCode($id, $user['User']['resetcode']),
                          "The resetcode validates.");
        $this->assertFalse($this->User->checkResetCode($id, 'bla'), "Invalid resetcode fails.");

        $this->User->execute("UPDATE users SET resetcode_expires='0000-00-00 00:00:00'
                              WHERE id={$id}");
        $this->assertFalse($this->User->checkResetCode($id, $user['User']['resetcode']),
                           "An expired resetcode doesn't work.");

        $this->User->del($id);
    }
}
?>
