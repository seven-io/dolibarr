# Official [seven](https://www.seven.io) module for [DOLIBARR ERP CRM](https://www.dolibarr.org)

## Features

- Send SMS to Contacts, third-party associated with _Projects_, _Invoice_, _Supplier Order_
- Send Bulk SMS to specific third parties (_prospect_, _customer_, _countries_ + many more)
- Automatic SMS dispatch after _disabling/enabling_ _contacts_
- Automatic SMS dispatch after an _invoice_ has been _created_, _validated_, _updated_ or _paid_
- Automatic SMS dispatch after _status changes_ to _prospection_, _qualification_, _proposal_ or _negotiation_
- Automatic SMS dispatch after an _order_ has been is _created_, _validated_, _approved_, _refused_, _dispatched_
- User Property Placeholders

## Translations

Translations can be completed manually by editing files into directories *langs*.

## Installation

### From the ZIP file and GUI interface

- If you get the module in a zip file (like when downloading it from the market
  place [Dolistore](https://www.dolistore.com)), go into
  menu ```Home - Setup - Modules - Deploy external module``` and upload the zip file.

Note: If this screen tell you there is no custom directory, check your setup is correct:

- In your Dolibarr installation directory, edit the ```htdocs/conf/conf.php``` file and check that following lines are
  not commented:

    ```php
    //$dolibarr_main_url_root_alt ...
    //$dolibarr_main_document_root_alt ...
    ```

- Uncomment them if necessary (delete the leading ```//```) and assign a sensible value according to your Dolibarr
  installation

  For example :

    - UNIX:
        ```php
        $dolibarr_main_url_root_alt = '/custom';
        $dolibarr_main_document_root_alt = '/var/www/Dolibarr/htdocs/custom';
        ```

    - Windows:
        ```php
        $dolibarr_main_url_root_alt = '/custom';
        $dolibarr_main_document_root_alt = 'C:/My Web Sites/Dolibarr/htdocs/custom';
        ```

### From a GIT repository

- Clone the repository in ```$dolibarr_main_document_root_alt/seven```

```sh
cd ....../custom
git clone git@github.com:seven-io/dolibarr.git seven
```

### <a name="final_steps"></a>Final steps

From your browser:

- Log into Dolibarr as a super-administrator
- Go to "Setup" -> "Modules"
- You should now be able to find and enable the module

[![MIT](https://img.shields.io/badge/License-MIT-teal.svg)](LICENSE)
