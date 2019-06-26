# netsapiens_persistent_voicemail


A. Create a user:
 1. Extension 300
 2. First name 8201
 3. Last name must contain "PVM".  Like "PVM Bob Smith"
 4. Set it's answering rule to "Always Forward" to a cell phone to be called persistently.

B. Create an auto attendant:
 1. Extension: 8201
 2. Name: Bob Voicemail PN
 3. Record a greeting and set Dial Pad Menus to "Voicemail of 300".  Greeting something like: "You have a message in your persistenmt mailbox, please press 1 to listen to the message".
 
C. Run persistent_voicemail.php every so often.

The program will pull all the mailboxes with "PVM" in the Last Name, loop through them seeing if there is an unread message, and place a call to the "First Name" if the mailbox has an unread message.
