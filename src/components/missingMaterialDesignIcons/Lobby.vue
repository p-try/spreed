<template>
	<span :aria-hidden="!title"
		:aria-label="title"
		class="material-design-icon lobby-icon"
		role="img"
		v-bind="$attrs"
		@click="$emit('click', $event)">
		<svg :fill="fillColor"
			class="material-design-icon__svg"
			:width="size"
			:height="size"
			viewBox="0 0 24 24">
			<path d="M2 17h20v2H2zm11.84-9.21c.1-.24.16-.51.16-.79 0-1.1-.9-2-2-2s-2 .9-2 2c0 .28.06.55.16.79C6.25 8.6 3.27 11.93 3 16h18c-.27-4.07-3.25-7.4-7.16-8.21z" />
			<title v-if="title">{{ title }}</title>
		</svg>
	</span>
</template>

<script>
export default {
	name: 'Lobby',
	props: {
		title: {
			type: String,
			default: '',
		},
		fillColor: {
			type: String,
			default: 'currentColor',
		},
		size: {
			type: Number,
			default: 24,
		},
	},
}
</script>
