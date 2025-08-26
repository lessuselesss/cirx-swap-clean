export default defineNuxtRouteMiddleware((to) => {
  // Block access to debug pages in production
  if (process.env.NODE_ENV === 'production' && to.path.startsWith('/debug')) {
    throw createError({
      statusCode: 404,
      statusMessage: 'Page Not Found'
    })
  }
})