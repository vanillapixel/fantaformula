# Use Node.js 20 LTS for React Router Dom v7 compatibility  
FROM node:20-alpine3.20

# Set working directory
WORKDIR /app

# Copy package files first for better caching
COPY package*.json ./

# Install dependencies (cached unless package.json changes)
RUN npm install && npm cache clean --force

# Copy source code
COPY . .

# Expose port 3000 for development
EXPOSE 3000

# Set environment to development
ENV NODE_ENV=development

# Start development server with hot reload
CMD ["npm", "start"]
