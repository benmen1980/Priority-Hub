const { default: axios } = require('axios');
var priority = require('priority-web-sdk');

const formArgv = process.argv;

var configuration = {
	appname: 'demo',
	username: formArgv[2] ,
	password: formArgv[3] ,
	url: 'https://' + formArgv[4] ,
	tabulaini: formArgv[5] ,
	language: 3,
	profile: {
		company: formArgv[6] ,
	},
	devicename: 'Roy',
	appkey  : 'F40FFA79343C446A9931BA1177716F04',
	appid   : 'APP006'
};

let filter = {
	or: 0,

	ignorecase: 1,

	QueryValues: [
		{
			field: 'PARTNAME',

			fromval: formArgv[7] || '000',

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
			'LOGPART',
			onShowMessge,
			null,
			configuration.profile,
			1
		)
	)
	.then(async (form) => {
		let url = formArgv[8] ;

		let { dataURI, fileType } = await getImage(url);
		const splitFileType = fileType.split('/')
		const selectFileType = `.${splitFileType[splitFileType.length - 1]}`
		await form.setSearchFilter(filter);
		await form.getRows(1);

		await form.setActiveRow(1);

		let fileResult = await form.uploadDataUrl(
			dataURI,
			selectFileType,
			function (fileUploadResult) {
				//console.log('File upload progress' + fileUploadResult.progress);
			}
		);
		await form.fieldUpdate('EXTFILENAME', fileResult.file);
		await form.saveRow(0);

		await form.getFileUrl(fileResult.file);
		console.log("Form File URL  :", form.getFileUrl(fileResult.file))

	})
	.catch((err) => {
		console.log({ err });
	});

async function getImage(url) {
	let response = await axios.get(url, {
		responseType: 'arraybuffer',
	});


	let fileType = response.headers['content-type'];

	let dataURI =
		'data:' +
		response.headers['content-type'] +
		';base64,' +
		Buffer.from(response.data).toString('base64');

	return { dataURI, fileType };
}

function onShowMessge(message) {
	console.log({ message });
}
