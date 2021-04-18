var priority = require('priority-web-sdk');

const formArgv = process.argv;
var configuration = {
    appname: 'demo',
    username: formArgv[2] || 'demo',
    password: formArgv[3] || '1234567',
    appid : 'APP006',
    appkey : 'F40FFA79343C446A9931BA1177716F04',
    url: formArgv[4] || 'https://devpri.roi-holdings.com',
    tabulaini: formArgv[5] || 'tabula.ini',
    language: 3,
    profile: {
        company: formArgv[6] || 'demo2',
    },
    devicename: 'Roy',
};

let filter = {
    or: 0,

    ignorecase: 1,

    QueryValues: [
        {
            field: 'IVNUM',

            fromval: formArgv[7] || 'T_352',

            op: '=',

            sort: 0,

            isdesc: 0,
        },
    ],
};

priority
    .login(configuration)
    .then(() =>
        priority.formStart(
            formArgv[8],
            onShowMessge,
            null,
            configuration.profile,
            1
        )
    )
    .then(async (form) => {
        await form.setSearchFilter(filter);
        await form.getRows(1);
        await form.setActiveRow(1);
        form.activateStart('CLOSEANINVOICE', 'P').then(async (activateFormResponse) => {
            //console.log("activateFormResponse : ", activateFormResponse)
            let end = await form.activateEnd();
        }).catch(err => {
            console.log("err: ", err)
        });
    })
    .catch((err) => {
        console.log('catch error ' + JSON.stringify(err));
    });

function onShowMessge(message) {
    console.log(message.message);
}