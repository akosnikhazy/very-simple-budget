# Very Simple Budget
A small PHP application to keep track of your money. Self hosted, open source and free.

### Warning
><sub>I made this for myself. By the license, you can do whatever you want with it. It works for me. I keep track of my money with it the way that's comfortable for me. I made it because managing several accounts was annoying in Google Sheets. Also, existing budgeting apps are too convoluted and feature-rich (and expensive) for my own use case. This is the bare minimum a budgeting app needs, and the maximum I need.</sub>

# Features
- Log transactions with three easy buttons: Money In, Money Out, Move Money (between accounts). Select a category, and account and you are post it.
- Monthly budget in your base currency. Set limits on your spending by category. At a glance, you can see how much you can spend in a given category this month.
- Transaction ledger shows every transaction you made. You can disable transactions without deleting them to exclude them from every calculation.
- Add accounts, cash or digital, in every currency of the world. Note: only your base currency counts towards the budget.
- "Wants and needs" system lets you mark your spending as a want or a need. Wants are stuff that you do not have to spend on. Needs are things you have to. The budget shows the balance of these two, so you can see how you spend your money.
- Settings page lets you set up your base currency, turn off "wants or needs," and change your password. You can also download and back up the database.
- Responsive design lets you use it on computer and phone.

# Setup
<sub>Bear with me, it is not well tought out, but not hard either.</sub>
1. Copy everything to your server wherever you want it to run.
2. In `require/head.php` you should change the APPKEY value. This is the pepper for your password.
3. In `index.php` comment out the password generator and put your first password in the `var_dump($pw ->createPasswordHash('[your pw]'));` line. Run the application. Copy the username:passwordhash: text into the auth.yzhk file (keep all the ":" ). You can change the username in this file too.
4. Comment out the password generator in `index.php`.
5. After logging in go to settings and change your password properly. If you lose your password repeat steps 3 to 5.
6. Optional: change database name and auth file name in `head.php`, if you do so rename the files as such.
7. Absolutely needed: hide these files with .htaccess
  ```
  <FilesMatch "^(auth\.yzhk|main\.db)$">
      Require all denied
  </FilesMatch>
  ```
