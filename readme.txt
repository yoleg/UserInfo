## Purpose:
For complex userinfo needs. Based on the Profile and Peoples snippets, but major improvements are shared between the two snippets.

## Features:
* Supports extended, remote, and completely custom or calculated user data
* Reduces database calls by allowing you to share custom user data in all UserInfo snippet calls
* Gives more control over the placeholders and data displayed
* Is easy to extend with your own class and methods
* Can easily be integrated with friendly URL solutions such as UserUrls

## Roadmap
* Add more support for groups
* Add UserInfo::get() method to check if a value exists yet in $UserInfo::data, and if not fetch it from the database.

## Extending
* If your new class is named UserInfoCustom, you should put it in /core/components/userinfo/model/userinfo/custom/userinfocustom.class.php
* You can use the class with &class=`UserInfoCustom` in both the UserInfo and ProfilePlus snippets

## UserInfo 
* With UserUrls: [[!UserInfo? &get_prefix=`uu_`]]
* Current user: [[!UserInfo]]
* Specific user: [[!UserInfo? &user=`12`]]
* &use_get - grab the userId out of a GET parameter
* &get_param - the name of the GET parameter to use. Default: userid
* &get_prefix - A prefix to prepend to the &get_param. Defaults to none.
* &prefix - add prefix to placeholders
* &default_to_current - set to false to not fall back to current user
* &default -  output if user is not found
* &load_login_lexicon - just in case you need it (and have Login installed)

## ProfilePlus
* Usage is almost the same as the Profile snippet by splittingred

## Useful System Settings
* userinfo.class - the default classname (&class)
* userinfo.get_prefix - the default GET prefix (&get_prefix)
* userinfo.get_param - the default GET parameter name (&get_param)
