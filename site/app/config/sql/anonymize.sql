-- To be run when you want to anonymize an AMO database.  Note that this does NOT
-- clear out all confidential information and should not be considered approval to
-- distribute AMO databases openly.  This just clears out the bare minimum of data.
-- Talk to clouserw if you have questions.

UPDATE users SET 
    email=CONCAT('user',id,'@nowhere'), 
    password='sha512$santized',
    lastname=SUBSTRING(lastname,1,1), 
    bio=NULL, 
    confirmationcode='', 
    resetcode='',
    resetcode_expires='0000-00-00 00:00:00', 
    notes='';

TRUNCATE cache;

TRUNCATE cake_sessions;

TRUNCATE tshirt_requests;
