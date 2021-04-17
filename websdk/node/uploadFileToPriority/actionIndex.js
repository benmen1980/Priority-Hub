const { default: axios } = require('axios');
var priority = require('priority-web-sdk');

const formArgv = process.argv;
var configuration = {
	appname: 'demo',
	username: formArgv[2] || 'demo',
	password: formArgv[3] || '1234567',
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
		let url = formArgv[8] ||'https://s3.eu-west-2.amazonaws.com/growinginteractive/blog/nigella-2x.jpg';

		let { dataURI, fileType } = await getImage(url);
		const splitFileType = fileType.split('/')
		const selectFileType = `.${splitFileType[splitFileType.length - 1]}`
		await form.setSearchFilter(filter);
		await form.getRows(1);

		let activateFormResponse = await form.activateStart('CHUNIT', 'P');
		activateFormResponse = await activateFormResponse.proc.inputOptions(1, 1);
		const Input = {
			EditFields: [{
				field: 1,
				value: 'lb'
			}]
		}
		const procedure_resp = await activateFormResponse.proc.inputFields(1, Input)

		console.log("-------------------------------------------")

		return { form, dataURI, selectFileType }


	})
	.then(async (formResponse) => {
		const { form, dataURI, selectFileType } = formResponse;


		let filter = {
			or: 0,
			ignorecase: 1,
			QueryValues: [{
				field: "PARTNAME",
				fromval: formArgv[7] || '000',
				op: "=",
				sort: 0,
				isdesc: 0
			}]
		};
		await form.setSearchFilter(filter);
		await form.getRows(1);

		let subform = await form.startSubForm('PARTEXTFILE', onShowMessge, null);
		let subfileResult = await subform.uploadDataUrl(
			dataURI,
			selectFileType,
			function (fileUploadResult) {
				console.log('File upload progress' + fileUploadResult.progress);
			}
		);

		console.log("SubForm File URL  :", subform.getFileUrl(subfileResult.file))
		const getFileUrl = subform.getFileUrl(subfileResult.file)
		const splitFileURL = getFileUrl.split('/');
		await form.newRow();
		await form.fieldUpdate('EXTFILENAME', subfileResult.file);
		await form.fieldUpdate('EXTFILEDES', splitFileURL[splitFileURL.length - 1]);
		await form.saveRow(0);

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
