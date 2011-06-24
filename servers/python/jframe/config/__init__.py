#========================================================================================
#/obj/obj/method? (default: //main if unspecified)
#
#formats:
#	json > json-getpost
#	xml > xml-getpost
#	soap
#	raw > raw-getpost
#
#
class Config:
	tokens = { \
		# the token between objects in the object path, e.g. /obj1/obj2[/method]
		'object_seperator': r'/',
		# the token between the object path and the parameters, e.g. /obj/method?params
		'divider': r'?',
		# the token between arguments in the parameters, e.g. /obj/method?param1=val1&param2=val2
		'parameter_seperator': r'&'
		}

	# which services to enable within jframe
	services = ['debug', 'auto-doc']
	# which services are currently disabled
	services_disabled = []

	# which output encodings are supported
	output_encodings = ['raw', 'json', 'xml']

	# which input encodings are supported
	input_encoding = ['get_post']

