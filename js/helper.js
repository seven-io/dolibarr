function ucFirst(str) {
	return (str + '').replace(/^([a-z])|\s+([a-z])/g, function ($1) {
		return $1.toUpperCase()
	})
}

Object.defineProperty(Array.prototype, 'toChunk', {
	value: function (chunkSize) {
		const array = this
		return [].concat.apply([],
			array.map(function (elem, i) {
				return i % chunkSize ? [] : [array.slice(i, i + chunkSize)]
			})
		)
	}
})
