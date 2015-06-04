# LS-V1-Google-Analytics-oAuth2
Brings Lemonstand V1 Google Analytics signing method up to date with oAuth2

1. Login to your Google Developers Console.
2. Create a project (if you haven't already go one created)
3. Navigate to Apis under Apis & Auth and add the Analytics API to your project.
4. Navigate and Create credentials sidebar under Apis & Auth choose Web Application as your option, leave the Redirect URIs callback as it is (oauth2callback) prepend your domain (if not automatically pre-pended).
5. Logout of Lemonstand V1 Admin area.
6. Create a new Folder called googleanalytics in the Modules section of your Lemonstand V1 software.
7. Add the content of this folder to the newly created googleanalytics folder.
8. Login to Lemonstand V1, you will possibly see a massive alert on the dashboard, ignore this.
9. Navigate to System > Settings > Statistics and Dashboard
10. Enter your Google Developer Console credentials *NOTE* Making sure your Redirect URI is identical to the one used in Google Developer Console and Save.